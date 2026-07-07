<template>
  <div class="page-container">
    <AccountingTabNav />

    <div class="page-header">
      <div>
        <h1 class="page-title">{{ $t('accounting.periodsTitle') }}</h1>
        <p class="page-subtitle">{{ $t('accounting.periodsSubtitle') }}</p>
      </div>
    </div>

    <div v-if="actionError" class="action-error" role="alert" data-test="period-error">
      {{ actionError }}
      <button class="action-error-close" @click="actionError = ''">×</button>
    </div>

    <div v-if="closeSuccess" class="action-success" role="status" data-test="close-success">
      {{ closeSuccess }}
      <button class="action-error-close" @click="closeSuccess = ''">×</button>
    </div>

    <StateBlock v-if="loading" variant="loading" />
    <StateBlock v-else-if="years.length === 0" variant="empty" :title="$t('accounting.noFiscalYear')" />

    <div v-for="fy in years" :key="fy.id" class="card acc-fy">
      <div class="acc-fy-head">
        <h3>{{ $t('accounting.fiscalYear') }} {{ fy.label }}</h3>
        <span :class="fy.status === 'open' ? 'badge badge-success' : 'badge badge-gray'">
          {{ $t('accounting.fyStatus.' + fy.status) }}
        </span>
        <button v-if="fy.status !== 'closed'" class="btn btn-sm btn-secondary acc-fy-close" :data-test="`close-fy-${fy.label}`" @click="openClose(fy)">
          {{ $t('accounting.closeYear') }}
        </button>
      </div>
      <table class="data-table">
        <thead>
          <tr>
            <th>{{ $t('accounting.colPeriod') }}</th>
            <th>{{ $t('accounting.colRange') }}</th>
            <th>{{ $t('common.status') }}</th>
            <th>{{ $t('common.actions') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="p in fy.periods" :key="p.id">
            <td><strong>{{ p.label }}</strong></td>
            <td>{{ fmtDate(p.starts_on) }} → {{ fmtDate(p.ends_on) }}</td>
            <td>
              <span :class="badge(p.status)" :data-test="`period-status-${p.label}`">{{ $t('accounting.periodStatus.' + p.status) }}</span>
            </td>
            <td>
              <button v-if="p.status === 'open'" class="btn btn-sm btn-secondary" :data-test="`lock-${p.label}`" @click="lock(p)">
                {{ $t('accounting.lock') }}
              </button>
              <button v-else-if="p.status === 'locked'" class="btn btn-sm btn-ghost" :data-test="`unlock-${p.label}`" @click="openUnlock(p)">
                {{ $t('accounting.reopen') }}
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Clôture d'exercice : confirmation (opération lourde, génère le report-à-nouveau) -->
    <BaseModal v-model="closeModal.open" :title="$t('accounting.closeYearTitle', { label: closeModal.label })" size="sm">
      <p class="acc-hint">{{ $t('accounting.closeYearWarn') }}</p>
      <p v-if="closeModal.error" class="form-error" data-test="close-error">{{ closeModal.error }}</p>
      <template #footer>
        <button class="btn btn-ghost" @click="closeModal.open = false">{{ $t('common.cancel') }}</button>
        <button class="btn btn-primary" :disabled="closeModal.saving" data-test="close-submit" @click="submitClose">
          {{ $t('accounting.closeYear') }}
        </button>
      </template>
    </BaseModal>

    <!-- Réouverture contrôlée : motif OBLIGATOIRE -->
    <BaseModal v-model="unlockModal.open" :title="$t('accounting.reopenTitle', { label: unlockModal.label })" size="sm">
      <div class="form-group">
        <label class="form-label">{{ $t('accounting.reopenReason') }} *</label>
        <textarea v-model="unlockModal.reason" class="form-input" rows="2" data-test="unlock-reason"></textarea>
      </div>
      <p v-if="unlockModal.error" class="form-error">{{ unlockModal.error }}</p>
      <template #footer>
        <button class="btn btn-ghost" @click="unlockModal.open = false">{{ $t('common.cancel') }}</button>
        <button class="btn btn-primary" :disabled="!unlockModal.reason.trim()" data-test="unlock-submit" @click="submitUnlock">
          {{ $t('accounting.reopen') }}
        </button>
      </template>
    </BaseModal>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import AccountingTabNav from '../components/AccountingTabNav.vue'
import StateBlock from '@/shared/ui/StateBlock.vue'
import BaseModal from '@/shared/ui/BaseModal.vue'
import { formatDateShort } from '@/shared/utils/date'
import { formatMoney } from '@/shared/utils/money'
import { useAuthStore } from '@/stores/auth'
import { accountingService } from '../services/accountingService'
import { t } from '@/i18n'
import type { AccountingPeriod, FiscalYear } from '../types'

const auth        = useAuthStore()
const currency    = (auth.user as any)?.tenant?.settings?.currency ?? 'XOF'
const years       = ref<FiscalYear[]>([])
const loading     = ref(false)
const actionError = ref('')
const closeSuccess = ref('')
const fmtDate     = formatDateShort

async function load() {
  loading.value = true
  try { years.value = await accountingService.fiscalYears() }
  finally { loading.value = false }
}

function badge(status: string): string {
  return 'badge ' + ({ open: 'badge-success', locked: 'badge-warning', closed: 'badge-gray' }[status] ?? 'badge-gray')
}

async function lock(p: AccountingPeriod) {
  actionError.value = ''
  try {
    await accountingService.lockPeriod(p.id)
    await load()
  } catch (e: any) {
    actionError.value = e?.response?.data?.message ?? t('accounting.lockError')
  }
}

// ── Clôture d'exercice ────────────────────────────────────────────────────────
const closeModal = reactive({ open: false, saving: false, id: '', label: '', error: '' })

function openClose(fy: FiscalYear) {
  Object.assign(closeModal, { open: true, saving: false, id: fy.id, label: fy.label, error: '' })
}

async function submitClose() {
  closeModal.saving = true
  closeModal.error = ''
  try {
    const res = await accountingService.closeFiscalYear(closeModal.id)
    closeModal.open = false
    const result = formatMoney(Math.abs(res.result_minor), currency)
    const kind = res.result_minor >= 0 ? t('accounting.profit') : t('accounting.loss')
    closeSuccess.value = t('accounting.closeYearDone', { year: closeModal.label, next: res.next_year.label, ran: res.carry_forward_entry.number ?? '', kind, result })
    await load()
  } catch (e: any) {
    closeModal.error = e?.response?.data?.message
      ?? (Object.values(e?.response?.data?.errors ?? {}).flat()[0] as string)
      ?? t('accounting.closeYearError')
  } finally {
    closeModal.saving = false
  }
}

const unlockModal = reactive({ open: false, id: '', label: '', reason: '', error: '' })

function openUnlock(p: AccountingPeriod) {
  Object.assign(unlockModal, { open: true, id: p.id, label: p.label, reason: '', error: '' })
}

async function submitUnlock() {
  unlockModal.error = ''
  try {
    await accountingService.unlockPeriod(unlockModal.id, unlockModal.reason.trim())
    unlockModal.open = false
    await load()
  } catch (e: any) {
    unlockModal.error = e?.response?.data?.message ?? t('accounting.reopenError')
  }
}

onMounted(load)
</script>

<style scoped>
.page-container { padding: 24px; max-width: 1100px; margin: 0 auto; }
.page-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; }
.page-title { font-size: 1.5rem; font-weight: 700; margin: 0; }
.page-subtitle { color: var(--gray-500); margin: 4px 0 0; font-size: 0.875rem; }
.acc-fy { margin-bottom: 20px; }
.acc-fy-head { display: flex; align-items: center; gap: 12px; padding: 4px 0 12px; }
.acc-fy-head h3 { margin: 0; font-size: 1.05rem; }
.acc-fy-close { margin-left: auto; }
.action-error { display: flex; justify-content: space-between; align-items: center; gap: 12px; background: #fee2e2; color: #991b1b; border-radius: 8px; padding: 10px 14px; margin-bottom: 16px; font-size: 0.875rem; }
.action-success { display: flex; justify-content: space-between; align-items: center; gap: 12px; background: #dcfce7; color: #166534; border-radius: 8px; padding: 10px 14px; margin-bottom: 16px; font-size: 0.875rem; }
.action-error-close { border: none; background: transparent; color: inherit; font-size: 1.1rem; cursor: pointer; }
.acc-hint { font-size: .85rem; color: var(--gray-600); }
.form-error { color: #dc2626; font-size: .85rem; }
</style>
