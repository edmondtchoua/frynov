<template>
  <div class="page-container">
    <InventoryTabNav />
    <div class="page-header">
      <div>
        <h1 class="page-title">{{ $t('inventory.transfersTitle') }}</h1>
        <p class="page-subtitle">{{ $t('inventory.transfersCount', { count: transfers.length }) }}</p>
      </div>
      <button class="btn btn-primary" @click="openCreate">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        {{ $t('inventory.newTransfer') }}
      </button>
    </div>

    <div class="filter-bar">
      <select v-model="filterStatus" class="form-input filter-select">
        <option value="">{{ $t('common.allStatuses') }}</option>
        <option value="draft">{{ $t('inventory.transferStatus.draft') }}</option>
        <option value="in_transit">{{ $t('inventory.transferStatus.in_transit') }}</option>
        <option value="partial">{{ $t('inventory.transferStatus.partial') }}</option>
        <option value="received">{{ $t('inventory.transferStatus.received') }}</option>
        <option value="completed">{{ $t('inventory.transferStatus.completed') }}</option>
        <option value="disputed">{{ $t('inventory.transferStatus.disputed') }}</option>
      </select>
    </div>

    <div class="card">
      <table class="data-table">
        <thead>
          <tr>
            <th>{{ $t('inventory.colNumber') }}</th>
            <th>{{ $t('inventory.colSource') }}</th>
            <th>{{ $t('inventory.colDestination') }}</th>
            <th>{{ $t('common.status') }}</th>
            <th>{{ $t('inventory.shippedOn') }}</th>
            <th>{{ $t('common.actions') }}</th>
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
              <button v-if="t.status === 'draft'" class="btn btn-sm btn-primary" @click="ship(t)">{{ $t('inventory.ship') }}</button>
              <button v-if="t.status === 'in_transit'" class="btn btn-sm btn-blue" @click="openReceive(t)">{{ $t('inventory.receive') }}</button>
              <button v-if="['partial','disputed'].includes(t.status)" class="btn btn-sm btn-danger" @click="openResolve(t)">{{ $t('inventory.resolve') }}</button>
            </td>
          </tr>
          <tr v-if="filteredTransfers.length === 0">
            <td colspan="6" class="empty-state">{{ $t('inventory.noTransfers') }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Create Modal (shared BaseModal — UX-03) -->
    <BaseModal v-model="showCreate" size="lg" :title="$t('inventory.newTransfer')">
      <div class="form-group">
        <label class="form-label">{{ $t('inventory.sourceWarehouse') }} *</label>
        <select v-model="form.source_warehouse_id" class="form-input">
          <option value="">{{ $t('inventory.selectEllipsis') }}</option>
          <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }} ({{ w.code }})</option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">{{ $t('inventory.destWarehouse') }} *</label>
        <select v-model="form.destination_warehouse_id" class="form-input">
          <option value="">{{ $t('inventory.selectEllipsis') }}</option>
          <option
            v-for="w in warehouses.filter(w => w.id !== form.source_warehouse_id)"
            :key="w.id" :value="w.id"
          >{{ w.name }} ({{ w.code }})</option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">{{ $t('common.notes') }}</label>
        <textarea v-model="form.notes" class="form-input" rows="2" :placeholder="$t('inventory.carrierPlaceholder')"></textarea>
      </div>
      <div class="form-group">
        <label class="form-label">{{ $t('inventory.lines') }} *</label>
        <div v-for="(line, i) in form.lines" :key="i" class="transfer-line">
          <input v-model="line.product_id" class="form-input" :placeholder="$t('inventory.productIdPlaceholder')" />
          <input v-model.number="line.quantity" type="number" class="form-input qty-input" min="1" :placeholder="$t('common.quantity')" />
          <button class="btn btn-sm btn-danger" @click="form.lines.splice(i,1)">✕</button>
        </div>
        <button class="btn btn-sm btn-secondary mt-2" @click="form.lines.push({product_id:'',quantity:1})">
          {{ $t('inventory.addLine') }}
        </button>
      </div>

      <template #footer>
        <button class="btn btn-secondary" @click="showCreate = false">{{ $t('common.cancel') }}</button>
        <button class="btn btn-primary" :disabled="creating" @click="createTransfer">
          {{ creating ? $t('inventory.creating') : $t('inventory.createTransfer') }}
        </button>
      </template>
    </BaseModal>

    <!-- Receive Modal (shared BaseModal — UX-03) -->
    <BaseModal
      :model-value="showReceive"
      :title="$t('inventory.receiveTransferTitle')"
      :subtitle="selectedTransfer?.number"
      @update:model-value="(v: boolean) => { if (!v) showReceive = false }"
    >
      <template v-if="selectedTransfer">
        <p class="modal-info">{{ $t('inventory.receiveHint') }}</p>
        <div v-for="line in selectedTransfer.lines" :key="line.id" class="receive-line">
          <span class="receive-label">{{ line.product?.name ?? line.product_id }}</span>
          <span class="receive-shipped">{{ $t('inventory.shipped') }}: <strong>{{ line.quantity_shipped }}</strong></span>
          <input
            v-model.number="receiveQtys[line.id]"
            type="number" class="form-input receive-qty"
            :max="line.quantity_shipped" :min="0"
            :placeholder="String(line.quantity_shipped)"
          />
        </div>
      </template>

      <template #footer>
        <button class="btn btn-secondary" @click="showReceive = false">{{ $t('common.cancel') }}</button>
        <button class="btn btn-primary" :disabled="receiving" @click="receiveTransfer">
          {{ receiving ? $t('common.saving') : $t('inventory.confirmReceive') }}
        </button>
      </template>
    </BaseModal>

    <!-- Resolve Modal (shared BaseModal — UX-03) -->
    <BaseModal
      :model-value="showResolve"
      :title="$t('inventory.resolveDisputeTitle')"
      :subtitle="selectedTransfer?.number"
      @update:model-value="(v: boolean) => { if (!v) showResolve = false }"
    >
      <div class="form-group">
        <label class="form-label">{{ $t('inventory.resolution') }} *</label>
        <select v-model="resolveForm.resolution" class="form-input">
          <option value="accept_partial">{{ $t('inventory.resolutionOpt.acceptPartial') }}</option>
          <option value="restock_source">{{ $t('inventory.resolutionOpt.restockSource') }}</option>
          <option value="write_off">{{ $t('inventory.resolutionOpt.writeOff') }}</option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">{{ $t('inventory.reason') }} *</label>
        <textarea v-model="resolveForm.reason" class="form-input" rows="2" :placeholder="$t('inventory.explainPlaceholder')"></textarea>
      </div>

      <template #footer>
        <button class="btn btn-secondary" @click="showResolve = false">{{ $t('common.cancel') }}</button>
        <button class="btn btn-danger" :disabled="resolving || !resolveForm.reason" @click="resolveDispute">
          {{ resolving ? $t('inventory.resolving') : $t('common.confirm') }}
        </button>
      </template>
    </BaseModal>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { formatDate } from '@/shared/utils/date'
import InventoryTabNav from "../components/InventoryTabNav.vue"
import BaseModal from '@/shared/ui/BaseModal.vue'
import { useConfirm } from '@/composables/useConfirm'
import api from '@/services/api'
import { t } from '@/i18n'

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

const { confirm } = useConfirm()

async function ship(tr: Transfer) {
  if (!(await confirm({
    title: t('inventory.shipTransferTitle'),
    message: t('inventory.shipTransferConfirm', { number: tr.number }),
    confirmLabel: t('inventory.ship'),
  }))) return
  await api.post(`/inventory/transfers/${tr.id}/ship`, {})
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
  return t(`inventory.transferStatus.${s}`)
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
/* Modal chrome now provided by the shared <BaseModal> (UX-03). */
.modal-info      { color: #64748b; font-size: 0.875rem; margin-bottom: 16px; }
.transfer-line   { display: flex; gap: 8px; margin-bottom: 8px; align-items: center; }
.qty-input       { width: 80px !important; }
.receive-line    { display: flex; gap: 12px; align-items: center; margin-bottom: 12px; padding: 8px; background: #f8fafc; border-radius: 8px; }
.receive-label   { flex: 1; font-weight: 500; font-size: 0.875rem; }
.receive-shipped { color: #64748b; font-size: 0.875rem; white-space: nowrap; }
.receive-qty     { width: 90px !important; }
.mt-2            { margin-top: 8px; }
</style>
