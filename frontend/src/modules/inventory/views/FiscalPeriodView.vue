<template>
  <div class="page-container">
    <InventoryTabNav />
    <div class="page-header">
      <div>
        <h1 class="page-title">Périodes fiscales</h1>
        <p class="page-subtitle">Verrouillage comptable irréversible des périodes clôturées</p>
      </div>
      <button class="btn btn-primary" @click="showCreate = true">+ Nouvelle période</button>
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
          Verrouillée le {{ fmtDate(p.locked_at) }}
          <span v-if="p.integrityOk !== undefined"
            :class="p.integrityOk ? 'badge badge-success' : 'badge badge-error'"
            style="margin-left:6px; font-size:0.7rem">
            {{ p.integrityOk ? '✓ Intégrité OK' : '✗ Altération détectée !' }}
          </span>
        </div>

        <div class="period-actions">
          <button
            v-if="p.status === 'open'"
            class="btn btn-sm btn-danger"
            @click="openLock(p)"
          >
            🔒 Verrouiller définitivement
          </button>
          <button
            v-if="p.status === 'locked'"
            class="btn btn-sm btn-secondary"
            @click="verifyIntegrity(p)"
          >
            Vérifier intégrité
          </button>
        </div>
      </div>

      <div v-if="periods.length === 0" class="empty-state">
        Aucune période fiscale définie — créez la première pour commencer la gestion comptable.
      </div>
    </div>

    <!-- Create Modal -->
    <div v-if="showCreate" class="modal-overlay" @click.self="showCreate = false">
      <div class="modal-card">
        <h2 class="modal-title">Nouvelle période fiscale</h2>
        <div class="form-group">
          <label class="form-label">Nom *</label>
          <input v-model="form.name" class="form-input" placeholder="Ex: Exercice 2025" />
        </div>
        <div class="form-group">
          <label class="form-label">Type *</label>
          <select v-model="form.type" class="form-input">
            <option value="annual">Annuel</option>
            <option value="quarterly">Trimestriel</option>
            <option value="monthly">Mensuel</option>
          </select>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Début *</label>
            <input v-model="form.starts_at" type="date" class="form-input" />
          </div>
          <div class="form-group">
            <label class="form-label">Fin *</label>
            <input v-model="form.ends_at" type="date" class="form-input" />
          </div>
        </div>
        <div class="modal-actions">
          <button class="btn btn-secondary" @click="showCreate = false">Annuler</button>
          <button class="btn btn-primary" :disabled="saving || !form.name || !form.starts_at || !form.ends_at" @click="createPeriod">
            {{ saving ? 'Création...' : 'Créer' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Lock Confirmation Modal -->
    <div v-if="lockTarget" class="modal-overlay" @click.self="lockTarget = null">
      <div class="modal-card modal-danger">
        <h2 class="modal-title">⚠️ Verrouillage irréversible</h2>
        <div class="warning-box">
          Cette action est <strong>définitive et irréversible</strong>. Une fois verrouillée,
          aucune écriture de stock ne pourra être effectuée sur la période
          <strong>{{ lockTarget.name }}</strong>
          ({{ fmtDate(lockTarget.starts_at) }} → {{ fmtDate(lockTarget.ends_at) }}).
        </div>
        <div class="form-group">
          <label class="form-label">Raison du verrouillage *</label>
          <input
            v-model="lockReason"
            class="form-input"
            placeholder="Ex: Clôture exercice 2025 approuvée en conseil de direction"
          />
        </div>
        <div class="modal-actions">
          <button class="btn btn-secondary" @click="lockTarget = null">Annuler</button>
          <button
            class="btn btn-danger"
            :disabled="!lockReason || locking"
            @click="confirmLock"
          >
            {{ locking ? 'Verrouillage...' : '🔒 Confirmer le verrouillage' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import InventoryTabNav from "../components/InventoryTabNav.vue"
import api from '@/services/api'

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

function fmtDate(d?: string): string {
  return d ? new Date(d).toLocaleDateString('fr-FR') : '—'
}
function typeLabel(t: string): string {
  return { annual: 'Annuel', quarterly: 'Trimestriel', monthly: 'Mensuel' }[t] ?? t
}
function statusLabel(s: string): string {
  return { open: 'Ouverte', review: 'En révision', locked: 'Verrouillée' }[s] ?? s
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
.modal-overlay   { position: fixed; inset: 0; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 50; }
.modal-card      { background: white; border-radius: 12px; padding: 28px; width: 520px; max-width: 95vw; }
.modal-danger    { border: 2px solid #fca5a5; }
.modal-title     { font-size: 1.125rem; font-weight: 600; margin: 0 0 16px; }
.modal-actions   { display: flex; gap: 8px; justify-content: flex-end; margin-top: 20px; }
.warning-box     { background: #fef2f2; border: 1px solid #fca5a5; border-radius: 8px; padding: 12px; color: #b91c1c; font-size: 0.875rem; line-height: 1.5; margin-bottom: 16px; }
.form-row        { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
</style>
