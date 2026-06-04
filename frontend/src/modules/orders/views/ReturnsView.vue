<template>
  <div class="page-container">
    <SalesTabNav />
    <div class="page-header">
      <div>
        <h1 class="page-title">Retours & SAV</h1>
        <p class="page-subtitle">{{ total }} retour(s) enregistré(s)</p>
      </div>
    </div>

    <div class="filter-bar">
      <select v-model="filterStatus" class="form-input filter-select" @change="load">
        <option value="">Tous les statuts</option>
        <option value="pending">En attente</option>
        <option value="approved">Approuvé</option>
        <option value="restocked">Remis en stock</option>
        <option value="rejected">Refusé</option>
        <option value="cancelled">Annulé</option>
      </select>
    </div>

    <div class="card">
      <div v-if="loading" class="loading-center"><div class="spinner-sm"></div></div>
      <table v-else class="data-table">
        <thead>
          <tr>
            <th>Numéro</th>
            <th>Commande</th>
            <th>Motif</th>
            <th>Résolution</th>
            <th>Statut</th>
            <th>Date</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="r in returns" :key="r.id">
            <td><strong>{{ r.number }}</strong></td>
            <td>{{ r.order?.number ?? '—' }}</td>
            <td>{{ reasonLabel(r.reason) }}</td>
            <td>{{ resolutionLabel(r.resolution) }}</td>
            <td><span :class="statusBadge(r.status)">{{ statusLabel(r.status) }}</span></td>
            <td>{{ fmtDate(r.created_at) }}</td>
            <td class="actions-cell">
              <button v-if="r.status === 'pending'" class="btn btn-sm btn-primary" @click="approve(r)">
                Approuver
              </button>
              <button v-if="r.status === 'approved'" class="btn btn-sm btn-blue" @click="restock(r)">
                Remettre en stock
              </button>
              <button v-if="r.status === 'pending'" class="btn btn-sm btn-danger" @click="openReject(r)">
                Refuser
              </button>
            </td>
          </tr>
          <tr v-if="returns.length === 0 && !loading">
            <td colspan="7" class="empty-state">Aucun retour trouvé</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Reject Modal -->
    <div v-if="rejectTarget" class="modal-overlay" @click.self="rejectTarget = null">
      <div class="modal-card">
        <h2 class="modal-title">Refuser le retour {{ rejectTarget.number }}</h2>
        <div class="form-group">
          <label class="form-label">Raison du refus *</label>
          <textarea
            v-model="rejectReason"
            class="form-input"
            rows="3"
            placeholder="Ex: Délai de retour dépassé (30 jours), article utilisé..."
          ></textarea>
        </div>
        <div class="modal-actions">
          <button class="btn btn-secondary" @click="rejectTarget = null">Annuler</button>
          <button class="btn btn-danger" :disabled="!rejectReason || rejecting" @click="confirmReject">
            {{ rejecting ? 'Refus en cours...' : 'Confirmer le refus' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { formatDate } from '@/shared/utils/date'
import api from '@/services/api'
import SalesTabNav from '../components/SalesTabNav.vue'

interface OrderReturn {
  id: string
  number: string
  status: string
  reason: string
  resolution: string
  created_at: string
  order?: { number: string }
}

const returns       = ref<OrderReturn[]>([])
const total         = ref(0)
const loading       = ref(false)
const filterStatus  = ref('')
const rejectTarget  = ref<OrderReturn | null>(null)
const rejectReason  = ref('')
const rejecting     = ref(false)

async function load() {
  loading.value = true
  try {
    const r = await api.get('/orders/returns', {
      params: { status: filterStatus.value || undefined },
    })
    const data = r.data
    returns.value = data.data ?? data
    total.value   = data.total ?? returns.value.length
  } finally { loading.value = false }
}

async function approve(r: OrderReturn) {
  if (!confirm(`Approuver le retour ${r.number} ?`)) return
  await api.post(`/orders/returns/${r.id}/approve`, {})
  await load()
}

async function restock(r: OrderReturn) {
  if (!confirm(`Remettre en stock les articles de ${r.number} ?`)) return
  await api.post(`/orders/returns/${r.id}/restock`, {})
  await load()
}

function openReject(r: OrderReturn) {
  rejectTarget.value = r
  rejectReason.value = ''
}

async function confirmReject() {
  if (!rejectTarget.value || !rejectReason.value) return
  rejecting.value = true
  try {
    await api.post(`/orders/returns/${rejectTarget.value.id}/reject`, {
      reason: rejectReason.value,
    })
    rejectTarget.value = null
    await load()
  } finally { rejecting.value = false }
}

function reasonLabel(r: string): string {
  return {
    defective: 'Défectueux', wrong_item: 'Mauvais article',
    changed_mind: "Changement d'avis", damaged: 'Endommagé', other: 'Autre',
  }[r] ?? r
}
function resolutionLabel(r: string): string {
  return { refund: 'Remboursement', exchange: 'Échange', store_credit: 'Avoir' }[r] ?? r
}
function statusLabel(s: string): string {
  return {
    pending: 'En attente', approved: 'Approuvé', processing: 'Traitement',
    restocked: 'Remis en stock', rejected: 'Refusé', cancelled: 'Annulé',
  }[s] ?? s
}
function statusBadge(s: string): string {
  const m: Record<string, string> = {
    pending: 'badge-warning', approved: 'badge-blue', restocked: 'badge-success',
    rejected: 'badge-error', cancelled: 'badge-gray',
  }
  return `badge ${m[s] ?? 'badge-gray'}`
}
const fmtDate = formatDate

onMounted(load)
</script>

<style scoped>
.page-container { padding: 24px; max-width: 1200px; margin: 0 auto; }
.page-header    { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px; }
.page-title     { font-size: 1.5rem; font-weight: 700; margin: 0; }
.page-subtitle  { color: #64748b; margin: 4px 0 0; font-size: 0.875rem; }
.filter-bar     { margin-bottom: 16px; }
.filter-select  { max-width: 220px; }
.actions-cell   { display: flex; gap: 4px; flex-wrap: wrap; }
.modal-overlay  { position: fixed; inset: 0; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 50; }
.modal-card     { background: white; border-radius: 12px; padding: 28px; width: 480px; max-width: 95vw; }
.modal-title    { font-size: 1.125rem; font-weight: 600; margin: 0 0 16px; }
.modal-actions  { display: flex; gap: 8px; justify-content: flex-end; margin-top: 20px; }
</style>
