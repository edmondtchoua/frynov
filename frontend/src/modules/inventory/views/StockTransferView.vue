<template>
  <div class="page-container">
    <InventoryTabNav />
    <div class="page-header">
      <div>
        <h1 class="page-title">Transferts inter-entrepôts</h1>
        <p class="page-subtitle">{{ transfers.length }} transfert(s)</p>
      </div>
      <button class="btn btn-primary" @click="openCreate">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Nouveau transfert
      </button>
    </div>

    <div class="filter-bar">
      <select v-model="filterStatus" class="form-input filter-select">
        <option value="">Tous les statuts</option>
        <option value="draft">Brouillon</option>
        <option value="in_transit">En transit</option>
        <option value="partial">Partiel</option>
        <option value="received">Reçu</option>
        <option value="completed">Terminé</option>
        <option value="disputed">Litige</option>
      </select>
    </div>

    <div class="card">
      <table class="data-table">
        <thead>
          <tr>
            <th>Numéro</th>
            <th>Source</th>
            <th>Destination</th>
            <th>Statut</th>
            <th>Expédié le</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="t in filteredTransfers" :key="t.id">
            <td><strong>{{ t.number }}</strong></td>
            <td>{{ t.source_warehouse?.name ?? '—' }}</td>
            <td>{{ t.destination_warehouse?.name ?? '—' }}</td>
            <td><span :class="statusBadge(t.status)">{{ statusLabel(t.status) }}</span></td>
            <td>{{ t.shipped_at ? fmtDate(t.shipped_at) : '—' }}</td>
            <td class="actions-cell">
              <button v-if="t.status === 'draft'" class="btn btn-sm btn-primary" @click="ship(t)">Expédier</button>
              <button v-if="t.status === 'in_transit'" class="btn btn-sm btn-blue" @click="openReceive(t)">Réceptionner</button>
              <button v-if="['partial','disputed'].includes(t.status)" class="btn btn-sm btn-danger" @click="openResolve(t)">Résoudre</button>
            </td>
          </tr>
          <tr v-if="filteredTransfers.length === 0">
            <td colspan="6" class="empty-state">Aucun transfert trouvé</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Create Modal -->
    <div v-if="showCreate" class="modal-overlay" @click.self="showCreate = false">
      <div class="modal-card">
        <h2 class="modal-title">Nouveau transfert</h2>
        <div class="form-group">
          <label class="form-label">Entrepôt source *</label>
          <select v-model="form.source_warehouse_id" class="form-input">
            <option value="">Sélectionner...</option>
            <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }} ({{ w.code }})</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Entrepôt destination *</label>
          <select v-model="form.destination_warehouse_id" class="form-input">
            <option value="">Sélectionner...</option>
            <option
              v-for="w in warehouses.filter(w => w.id !== form.source_warehouse_id)"
              :key="w.id" :value="w.id"
            >{{ w.name }} ({{ w.code }})</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Notes</label>
          <textarea v-model="form.notes" class="form-input" rows="2" placeholder="Transporteur, instructions..."></textarea>
        </div>
        <div class="form-group">
          <label class="form-label">Lignes *</label>
          <div v-for="(line, i) in form.lines" :key="i" class="transfer-line">
            <input v-model="line.product_id" class="form-input" placeholder="Product ID" />
            <input v-model.number="line.quantity" type="number" class="form-input qty-input" min="1" placeholder="Qté" />
            <button class="btn btn-sm btn-danger" @click="form.lines.splice(i,1)">✕</button>
          </div>
          <button class="btn btn-sm btn-secondary mt-2" @click="form.lines.push({product_id:'',quantity:1})">
            + Ajouter ligne
          </button>
        </div>
        <div class="modal-actions">
          <button class="btn btn-secondary" @click="showCreate = false">Annuler</button>
          <button class="btn btn-primary" :disabled="creating" @click="createTransfer">
            {{ creating ? 'Création...' : 'Créer le transfert' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Receive Modal -->
    <div v-if="showReceive && selectedTransfer" class="modal-overlay" @click.self="showReceive = false">
      <div class="modal-card">
        <h2 class="modal-title">Réceptionner {{ selectedTransfer.number }}</h2>
        <p class="modal-info">Saisissez les quantités réellement reçues pour chaque ligne.</p>
        <div v-for="line in selectedTransfer.lines" :key="line.id" class="receive-line">
          <span class="receive-label">{{ line.product?.name ?? line.product_id }}</span>
          <span class="receive-shipped">Expédié: <strong>{{ line.quantity_shipped }}</strong></span>
          <input
            v-model.number="receiveQtys[line.id]"
            type="number" class="form-input receive-qty"
            :max="line.quantity_shipped" :min="0"
            :placeholder="String(line.quantity_shipped)"
          />
        </div>
        <div class="modal-actions">
          <button class="btn btn-secondary" @click="showReceive = false">Annuler</button>
          <button class="btn btn-primary" :disabled="receiving" @click="receiveTransfer">
            {{ receiving ? 'Enregistrement...' : 'Confirmer réception' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Resolve Modal -->
    <div v-if="showResolve && selectedTransfer" class="modal-overlay" @click.self="showResolve = false">
      <div class="modal-card">
        <h2 class="modal-title">Résoudre litige — {{ selectedTransfer.number }}</h2>
        <div class="form-group">
          <label class="form-label">Résolution *</label>
          <select v-model="resolveForm.resolution" class="form-input">
            <option value="accept_partial">Accepter partiel</option>
            <option value="restock_source">Retour à la source</option>
            <option value="write_off">Perte (write-off)</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Raison *</label>
          <textarea v-model="resolveForm.reason" class="form-input" rows="2" placeholder="Expliquer..."></textarea>
        </div>
        <div class="modal-actions">
          <button class="btn btn-secondary" @click="showResolve = false">Annuler</button>
          <button class="btn btn-danger" :disabled="resolving || !resolveForm.reason" @click="resolveDispute">
            {{ resolving ? 'Résolution...' : 'Confirmer' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { formatDate } from '@/shared/utils/date'
import InventoryTabNav from "../components/InventoryTabNav.vue"
import api from '@/services/api'

interface Warehouse { id: string; name: string; code: string }
interface TransferLine {
  id: string; product_id: string; product?: { name: string }
  quantity_requested: number; quantity_shipped: number; line_status: string
}
interface Transfer {
  id: string; number: string; status: string
  source_warehouse?: Warehouse; destination_warehouse?: Warehouse
  shipped_at?: string; lines: TransferLine[]
}

const transfers      = ref<Transfer[]>([])
const warehouses     = ref<Warehouse[]>([])
const filterStatus   = ref('')
const showCreate     = ref(false)
const showReceive    = ref(false)
const showResolve    = ref(false)
const creating       = ref(false)
const receiving      = ref(false)
const resolving      = ref(false)
const selectedTransfer = ref<Transfer | null>(null)
const receiveQtys    = ref<Record<string, number>>({})
const resolveForm    = ref({ resolution: 'accept_partial', reason: '' })
const form           = ref({
  source_warehouse_id: '', destination_warehouse_id: '', notes: '',
  lines: [{ product_id: '', quantity: 1 }],
})

const filteredTransfers = computed(() =>
  filterStatus.value
    ? transfers.value.filter(t => t.status === filterStatus.value)
    : transfers.value
)

async function load() {
  const [tr, wh] = await Promise.all([
    api.get('/inventory/transfers'),
    api.get('/inventory/warehouses'),
  ])
  transfers.value = tr.data.data ?? tr.data
  warehouses.value = wh.data.data ?? wh.data
}

function openCreate() { showCreate.value = true }

function openReceive(t: Transfer) {
  selectedTransfer.value = t
  receiveQtys.value = Object.fromEntries(t.lines.map(l => [l.id, l.quantity_shipped]))
  showReceive.value = true
}

function openResolve(t: Transfer) {
  selectedTransfer.value = t
  resolveForm.value = { resolution: 'accept_partial', reason: '' }
  showResolve.value = true
}

async function createTransfer() {
  if (!form.value.source_warehouse_id || !form.value.destination_warehouse_id) return
  creating.value = true
  try {
    await api.post('/inventory/transfers', {
      source_warehouse_id:      form.value.source_warehouse_id,
      destination_warehouse_id: form.value.destination_warehouse_id,
      notes: form.value.notes,
      lines: form.value.lines.filter(l => l.product_id && l.quantity > 0),
    })
    showCreate.value = false
    form.value = { source_warehouse_id: '', destination_warehouse_id: '', notes: '', lines: [{ product_id: '', quantity: 1 }] }
    await load()
  } finally { creating.value = false }
}

async function ship(t: Transfer) {
  if (!confirm(`Expédier le transfert ${t.number} ?`)) return
  await api.post(`/inventory/transfers/${t.id}/ship`, {})
  await load()
}

async function receiveTransfer() {
  if (!selectedTransfer.value) return
  receiving.value = true
  try {
    await api.post(`/inventory/transfers/${selectedTransfer.value.id}/receive`, {
      quantities: receiveQtys.value,
    })
    showReceive.value = false
    await load()
  } finally { receiving.value = false }
}

async function resolveDispute() {
  if (!selectedTransfer.value || !resolveForm.value.reason) return
  resolving.value = true
  try {
    await api.post(`/inventory/transfers/${selectedTransfer.value.id}/resolve`, resolveForm.value)
    showResolve.value = false
    await load()
  } finally { resolving.value = false }
}

function statusLabel(s: string): string {
  const labels: Record<string, string> = {
    draft: 'Brouillon', requested: 'Demandé', in_transit: 'En transit',
    received: 'Reçu', partial: 'Partiel', completed: 'Terminé',
    disputed: 'Litige', cancelled: 'Annulé',
  }
  return labels[s] ?? s
}

function statusBadge(s: string): string {
  const m: Record<string, string> = {
    draft: 'badge-gray', in_transit: 'badge-blue', received: 'badge-success',
    partial: 'badge-warning', completed: 'badge-success',
    disputed: 'badge-error', cancelled: 'badge-gray',
  }
  return `badge ${m[s] ?? 'badge-gray'}`
}

const fmtDate = formatDate

onMounted(load)
</script>

<style scoped>
.page-container  { padding: 24px; max-width: 1200px; margin: 0 auto; }
.page-header     { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px; }
.page-title      { font-size: 1.5rem; font-weight: 700; color: #1e293b; margin: 0; }
.page-subtitle   { color: #64748b; margin: 4px 0 0; font-size: 0.875rem; }
.filter-bar      { margin-bottom: 16px; }
.filter-select   { max-width: 220px; }
.actions-cell    { display: flex; gap: 4px; flex-wrap: wrap; }
.modal-overlay   { position: fixed; inset: 0; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 50; }
.modal-card      { background: white; border-radius: 12px; padding: 28px; width: 560px; max-width: 95vw; max-height: 90vh; overflow-y: auto; }
.modal-title     { font-size: 1.125rem; font-weight: 600; margin: 0 0 16px; }
.modal-info      { color: #64748b; font-size: 0.875rem; margin-bottom: 16px; }
.modal-actions   { display: flex; gap: 8px; justify-content: flex-end; margin-top: 20px; }
.transfer-line   { display: flex; gap: 8px; margin-bottom: 8px; align-items: center; }
.qty-input       { width: 80px !important; }
.receive-line    { display: flex; gap: 12px; align-items: center; margin-bottom: 12px; padding: 8px; background: #f8fafc; border-radius: 8px; }
.receive-label   { flex: 1; font-weight: 500; font-size: 0.875rem; }
.receive-shipped { color: #64748b; font-size: 0.875rem; white-space: nowrap; }
.receive-qty     { width: 90px !important; }
.mt-2            { margin-top: 8px; }
</style>
