<template>
  <div class="page-container">
    <InventoryTabNav />
    <div class="page-header">
      <div>
        <h1 class="page-title">{{ $t('inventory.fiscalTitle') }}</h1>
        <p class="page-subtitle">{{ $t('inventory.fiscalSubtitle') }}</p>
      </div>
      <button class="btn btn-primary" @click="showCreate = true">+ {{ $t('inventory.newPeriod') }}</button>
    </div>

    <div v-if="loading" class="loading-center">
      <div class="spinner-sm"></div>
    </div>

    <div v-else class="periods-grid">
      <div v-for="p in periods" :key="p.id" :class="['period-card', p.status]">
        <div class="period-header">
          <div>
            <h3 class="period-name">{{ p.name }}</h3>
            <span class="period-type-badge">{{ typeLabel(p.type) }}</span>
          </div>
          <span :class="statusBadge(p.status)">{{ statusLabel(p.status) }}</span>
        </div>

        <div class="period-dates">
          📅 {{ fmtDate(p.starts_at) }} → {{ fmtDate(p.ends_at) }}
        </div>

        <div v-if="p.status === 'locked'" class="lock-info">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="3" y="11" width="18" height="11" rx="2"/>
            <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
          </svg>
          {{ $t('inventory.lockedOn') }} {{ fmtDate(p.locked_at) }}
          <span v-if="p.integrityOk !== undefined"
            :class="p.integrityOk ? 'badge badge-success' : 'badge badge-error'"
            style="margin-left:6px; font-size:0.7rem">
            {{ p.integrityOk ? $t('inventory.integrityOk') : $t('inventory.integrityFail') }}
          </span>
        </div>

        <div class="period-actions">
          <button
            v-if="p.status === 'open'"
            class="btn btn-sm btn-danger"
            @click="openLock(p)"
          >
            🔒 {{ $t('inventory.lockPermanently') }}
          </button>
          <button
            v-if="p.status === 'locked'"
            class="btn btn-sm btn-secondary"
            @click="verifyIntegrity(p)"
          >
            {{ $t('inventory.verifyIntegrity') }}
          </button>
        </div>
      </div>

      <div v-if="periods.length === 0" class="empty-state">
        {{ $t('inventory.emptyPeriods') }}
      </div>
    </div>

    <!-- Create Modal (shared BaseModal — UX-03) -->
    <BaseModal v-model="showCreate" size="lg" :title="$t('inventory.newPeriodTitle')">
      <div class="form-group">
        <label class="form-label">{{ $t('common.name') }} *</label>
        <input v-model="form.name" class="form-input" :placeholder="$t('inventory.periodNamePlaceholder')" />
      </div>
      <div class="form-group">
        <label class="form-label">{{ $t('inventory.type') }} *</label>
        <select v-model="form.type" class="form-input">
          <option value="annual">{{ $t('inventory.fiscalType.annual') }}</option>
          <option value="quarterly">{{ $t('inventory.fiscalType.quarterly') }}</option>
          <option value="monthly">{{ $t('inventory.fiscalType.monthly') }}</option>
        </select>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">{{ $t('inventory.startDate') }} *</label>
          <input v-model="form.starts_at" type="date" class="form-input" />
        </div>
        <div class="form-group">
          <label class="form-label">{{ $t('inventory.endDate') }} *</label>
          <input v-model="form.ends_at" type="date" class="form-input" />
        </div>
      </div>

      <template #footer>
        <button class="btn btn-secondary" @click="showCreate = false">{{ $t('common.cancel') }}</button>
        <button class="btn btn-primary" :disabled="saving || !form.name || !form.starts_at || !form.ends_at" @click="createPeriod">
          {{ saving ? $t('inventory.creating') : $t('common.create') }}
        </button>
      </template>
    </BaseModal>

    <!-- Lock Confirmation Modal (shared BaseModal — UX-03) -->
    <BaseModal
      :model-value="!!lockTarget"
      :title="$t('inventory.lockTitle')"
      :subtitle="lockTarget?.name"
      @update:model-value="(v: boolean) => { if (!v) lockTarget = null }"
    >
      <template v-if="lockTarget">
        <div class="warning-box">
          {{ $t('inventory.lockWarningStart') }} <strong>{{ $t('inventory.lockWarningStrong') }}</strong>{{ $t('inventory.lockWarningMid') }}
          <strong>{{ lockTarget.name }}</strong>
          ({{ fmtDate(lockTarget.starts_at) }} → {{ fmtDate(lockTarget.ends_at) }}).
        </div>
        <div class="form-group">
          <label class="form-label">{{ $t('inventory.lockReasonLabel') }} *</label>
          <input
            v-model="lockReason"
            class="form-input"
            :placeholder="$t('inventory.lockReasonPlaceholder')"
          />
        </div>
      </template>

      <template #footer>
        <button class="btn btn-secondary" @click="lockTarget = null">{{ $t('common.cancel') }}</button>
        <button
          class="btn btn-danger"
          :disabled="!lockReason || locking"
          @click="confirmLock"
        >
          {{ locking ? $t('inventory.locking') : '🔒 ' + $t('inventory.confirmLock') }}
        </button>
      </template>
    </BaseModal>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { formatDate } from '@/shared/utils/date'
import InventoryTabNav from "../components/InventoryTabNav.vue"
import BaseModal from '@/shared/ui/BaseModal.vue'
import api from '@/services/api'
import { t } from '@/i18n'

interface FiscalPeriod {
  id: string
  name: string
  type: string
  starts_at: string
  ends_at: string
  status: string
  locked_at?: string
  integrityOk?: boolean
}

const periods    = ref<FiscalPeriod[]>([])
const loading    = ref(false)
const showCreate = ref(false)
const saving     = ref(false)
const locking    = ref(false)
const lockTarget = ref<FiscalPeriod | null>(null)
const lockReason = ref('')
const form       = ref({ name: '', type: 'annual', starts_at: '', ends_at: '' })

async function load() {
  loading.value = true
  try {
    const r = await api.get('/inventory/fiscal-periods')
    periods.value = r.data.data ?? r.data
  } finally { loading.value = false }
}

function openLock(p: FiscalPeriod) {
  lockTarget.value = p
  lockReason.value = ''
}

async function createPeriod() {
  saving.value = true
  try {
    await api.post('/inventory/fiscal-periods', form.value)
    showCreate.value = false
    form.value = { name: '', type: 'annual', starts_at: '', ends_at: '' }
    await load()
  } finally { saving.value = false }
}

async function confirmLock() {
  if (!lockTarget.value || !lockReason.value) return
  locking.value = true
  try {
    await api.post(`/inventory/fiscal-periods/${lockTarget.value.id}/lock`, {
      reason: lockReason.value,
    })
    lockTarget.value = null
    await load()
  } finally { locking.value = false }
}

async function verifyIntegrity(p: FiscalPeriod) {
  const r = await api.get(`/inventory/fiscal-periods/${p.id}/verify`)
  const idx = periods.value.findIndex(x => x.id === p.id)
  if (idx >= 0) periods.value[idx] = { ...periods.value[idx], integrityOk: r.data.data.integrity_ok }
}

const fmtDate = formatDate
function typeLabel(type: string): string {
  return t(`inventory.fiscalType.${type}`)
}
function statusLabel(s: string): string {
  return t(`inventory.fiscalStatus.${s}`)
}
function statusBadge(s: string): string {
  return `badge ${s === 'locked' ? 'badge-error' : s === 'review' ? 'badge-warning' : 'badge-success'}`
}

onMounted(load)
</script>

<style scoped>
.page-container  { padding: 24px; max-width: 1100px; margin: 0 auto; }
.page-header     { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px; }
.page-title      { font-size: 1.5rem; font-weight: 700; margin: 0; }
.page-subtitle   { color: #64748b; margin: 4px 0 0; font-size: 0.875rem; }
.periods-grid    { display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 16px; }
.period-card     { background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; transition: box-shadow 0.2s; }
.period-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
.period-card.locked { border-color: #fca5a5; background: #fff5f5; }
.period-card.open   { border-color: #6ee7b7; }
.period-card.review { border-color: #fcd34d; background: #fffbeb; }
.period-header   { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px; }
.period-name     { font-weight: 600; font-size: 1.05rem; margin: 0 0 4px; }
.period-type-badge { font-size: 0.75rem; color: #64748b; background: #f1f5f9; padding: 2px 8px; border-radius: 20px; }
.period-dates    { font-size: 0.875rem; color: #475569; margin-bottom: 12px; }
.lock-info       { font-size: 0.8rem; color: #64748b; display: flex; align-items: center; gap: 4px; flex-wrap: wrap; margin-bottom: 12px; }
.period-actions  { display: flex; gap: 8px; }
/* Modal chrome now provided by the shared <BaseModal> (UX-03). */
.warning-box     { background: #fef2f2; border: 1px solid #fca5a5; border-radius: 8px; padding: 12px; color: #b91c1c; font-size: 0.875rem; line-height: 1.5; margin-bottom: 16px; }
.form-row        { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
</style>
