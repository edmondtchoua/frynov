<template>
  <div class="page-container">
    <SalesTabNav />
    <div class="page-header">
      <div>
        <h1 class="page-title">{{ $t('orders.returns.title') }}</h1>
        <p class="page-subtitle">{{ $t('orders.returns.subtitle', { count: total }) }}</p>
      </div>
    </div>

    <div class="filter-bar">
      <select v-model="filterStatus" class="form-input filter-select" @change="load">
        <option value="">{{ $t('common.allStatuses') }}</option>
        <option value="pending">{{ $t('orders.returns.status.pending') }}</option>
        <option value="approved">{{ $t('orders.returns.status.approved') }}</option>
        <option value="restocked">{{ $t('orders.returns.status.restocked') }}</option>
        <option value="rejected">{{ $t('orders.returns.status.rejected') }}</option>
        <option value="cancelled">{{ $t('orders.returns.status.cancelled') }}</option>
      </select>
    </div>

    <div class="card table-scroll">
      <StateBlock v-if="loading" variant="loading" />
      <StateBlock v-else-if="returns.length === 0" variant="empty" :title="$t('orders.returns.empty')" />
      <table v-else class="data-table">
        <thead>
          <tr>
            <th>{{ $t('orders.returns.colNumber') }}</th>
            <th>{{ $t('deliveries.colOrder') }}</th>
            <th>{{ $t('orders.returns.colReason') }}</th>
            <th>{{ $t('orders.returns.colResolution') }}</th>
            <th>{{ $t('common.status') }}</th>
            <th>{{ $t('common.date') }}</th>
            <th>{{ $t('common.actions') }}</th>
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
                {{ $t('orders.returns.approve') }}
              </button>
              <button v-if="r.status === 'approved'" class="btn btn-sm btn-blue" @click="restock(r)">
                {{ $t('orders.returns.restock') }}
              </button>
              <button v-if="r.status === 'pending'" class="btn btn-sm btn-danger" @click="openReject(r)">
                {{ $t('orders.returns.reject') }}
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Reject Modal (shared BaseModal — UX-03) -->
    <BaseModal
      :model-value="!!rejectTarget"
      size="sm"
      :title="rejectTarget ? $t('orders.returns.rejectTitle', { number: rejectTarget.number }) : ''"
      @update:model-value="(v: boolean) => { if (!v) rejectTarget = null }"
    >
      <div class="form-group">
        <label class="form-label">{{ $t('orders.returns.rejectReason') }} *</label>
        <textarea
          v-model="rejectReason"
          class="form-input"
          rows="3"
          :placeholder="$t('orders.returns.rejectPlaceholder')"
        ></textarea>
      </div>

      <template #footer>
        <button class="btn btn-secondary" @click="rejectTarget = null">{{ $t('common.cancel') }}</button>
        <button class="btn btn-danger" :disabled="!rejectReason || rejecting" @click="confirmReject">
          {{ rejecting ? $t('orders.returns.rejecting') : $t('orders.returns.confirmReject') }}
        </button>
      </template>
    </BaseModal>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { formatDate } from '@/shared/utils/date'
import api from '@/services/api'
import StateBlock from '@/shared/ui/StateBlock.vue'
import BaseModal from '@/shared/ui/BaseModal.vue'
import { useConfirm } from '@/composables/useConfirm'
import SalesTabNav from '../components/SalesTabNav.vue'
import { t } from '@/i18n'

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

const { confirm } = useConfirm()

async function approve(r: OrderReturn) {
  if (!(await confirm({
    title: t('orders.returns.approveTitle'),
    message: t('orders.returns.approveConfirm', { number: r.number }),
    confirmLabel: t('orders.returns.approve'),
  }))) return
  await api.post(`/orders/returns/${r.id}/approve`, {})
  await load()
}

async function restock(r: OrderReturn) {
  if (!(await confirm({
    title: t('orders.returns.restockTitle'),
    message: t('orders.returns.restockConfirm', { number: r.number }),
    confirmLabel: t('orders.returns.restock'),
  }))) return
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
  return t(`orders.returns.reason.${r}`)
}
function resolutionLabel(r: string): string {
  return t(`orders.returns.resolution.${r}`)
}
function statusLabel(s: string): string {
  return t(`orders.returns.status.${s}`)
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
/* Modal chrome now provided by the shared <BaseModal> (UX-03). */
</style>
