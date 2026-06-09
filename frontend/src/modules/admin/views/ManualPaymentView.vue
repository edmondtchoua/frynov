<template>
  <div class="payment-review">

    <!-- Action error banner -->
    <Transition name="fade">
      <div v-if="actionError" class="action-error-banner">
        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" style="flex-shrink:0">
          <circle cx="8" cy="8" r="6.5" stroke="currentColor" stroke-width="1.4"/>
          <path d="M8 5v3.5M8 10.5v.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
        </svg>
        {{ actionError }}
        <button class="banner-close" @click="actionError = ''">✕</button>
      </div>
    </Transition>

    <!-- Status filter tabs -->
    <div class="status-tabs">
      <button
        v-for="tab in statusTabs"
        :key="tab.value"
        class="status-tab"
        :class="{ active: statusFilter === tab.value }"
        @click="statusFilter = tab.value; page = 1; load()"
      >
        {{ tab.label }}
        <span v-if="tab.value === 'pending' && pendingCount > 0" class="tab-badge">{{ pendingCount }}</span>
      </button>
    </div>

    <!-- Table -->
    <div class="table-wrap table-scroll">
      <table class="admin-table" v-if="!loading && payments.length">
        <thead>
          <tr>
            <th>{{ $t('admin.colTenant') }}</th>
            <th>{{ $t('admin.planRequested') }}</th>
            <th>{{ $t('common.amount') }}</th>
            <th>{{ $t('admin.method') }}</th>
            <th>{{ $t('admin.proof') }}</th>
            <th>{{ $t('common.date') }}</th>
            <th>{{ $t('common.status') }}</th>
            <th>{{ $t('common.actions') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="p in payments" :key="p.id">
            <td>
              <RouterLink :to="`/admin/tenants/${p.tenant_id}`" class="link-tenant">
                {{ p.tenant_name }}
              </RouterLink>
            </td>
            <td><span class="plan-chip">{{ p.plan_code }}</span></td>
            <td class="bold">{{ formatAmount(p.amount_cents, p.currency) }}</td>
            <td class="dim">{{ methodLabel(p.payment_method) }}</td>
            <td>
              <a v-if="p.proof_url" :href="p.proof_url" target="_blank" class="proof-link">
                <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M2 12V3l3-1.5h5L12 3v9H2z" stroke="currentColor" stroke-width="1.2"/><path d="M5 6h4M5 8h3" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/></svg>
                {{ p.proof_original_filename ?? $t('common.view') }}
              </a>
              <span v-else class="dim">—</span>
            </td>
            <td class="dim">{{ fmtDate(p.created_at) }}</td>
            <td>
              <span :class="`status-badge status-badge--${p.status}`">{{ statusLabel(p.status) }}</span>
              <div v-if="p.rejection_reason" class="rejection-reason">{{ p.rejection_reason }}</div>
            </td>
            <td>
              <div v-if="p.status === 'pending'" class="action-group">
                <button class="btn-sm btn-sm--ok" @click="doApprove(p)">{{ $t('admin.approve') }}</button>
                <button class="btn-sm btn-sm--warn" @click="openReject(p)">{{ $t('admin.reject') }}</button>
              </div>
              <span v-else class="dim">—</span>
            </td>
          </tr>
        </tbody>
      </table>
      <StateBlock v-else-if="loading" variant="loading" />
      <StateBlock v-else variant="empty" :title="statusFilter ? $t('admin.noPaymentsStatus') : $t('admin.noPayments')" />
    </div>

    <!-- Pagination -->
    <div class="pagination" v-if="meta && meta.last_page > 1">
      <button :disabled="page === 1" @click="page--; load()">← {{ $t('common.previous') }}</button>
      <span>{{ $t('admin.pageOf', { current: meta.current_page, total: meta.last_page }) }}</span>
      <button :disabled="page === meta.last_page" @click="page++; load()">{{ $t('common.next') }} →</button>
    </div>

    <!-- Reject modal (shared BaseModal — UX-03) -->
    <BaseModal v-model="rejectModal.open" size="sm" :title="$t('admin.rejectPayment')" :subtitle="rejectModal.payment?.tenant_name">
      <p class="modal-desc">{{ $t('admin.rejectReasonPrompt') }}</p>
      <textarea
        v-model="rejectModal.reason"
        class="form-textarea"
        :placeholder="$t('admin.rejectReasonPlaceholder')"
        rows="3"
      ></textarea>
      <div v-if="rejectModal.error" class="form-error">{{ rejectModal.error }}</div>

      <template #footer>
        <button class="btn-cancel" @click="rejectModal.open = false">{{ $t('common.cancel') }}</button>
        <button class="btn-reject" :disabled="rejectModal.saving || !rejectModal.reason" @click="confirmReject">
          {{ rejectModal.saving ? '…' : $t('admin.confirmReject') }}
        </button>
      </template>
    </BaseModal>

  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { formatDate } from '@/shared/utils/date'
import { RouterLink } from 'vue-router'
import { formatMoney } from '@/shared/utils/money'
import StateBlock from '@/shared/ui/StateBlock.vue'
import BaseModal from '@/shared/ui/BaseModal.vue'
import { useConfirm } from '@/composables/useConfirm'
import { adminService, type AdminManualPayment } from '../services/adminService'
import { t } from '@/i18n'

// ── State ─────────────────────────────────────────────────────────────────────
const payments     = ref<AdminManualPayment[]>([])
const meta         = ref<any>(null)
const loading      = ref(true)
const page         = ref(1)
const statusFilter = ref('pending')
const pendingCount = ref(0)

const statusTabs = computed(() => [
  { value: 'pending',  label: t('admin.paymentTabs.pending') },
  { value: 'approved', label: t('admin.paymentTabs.approved') },
  { value: 'rejected', label: t('admin.paymentTabs.rejected') },
  { value: '',         label: t('admin.all') },
])

// ── Reject modal ──────────────────────────────────────────────────────────────
const rejectModal = reactive({
  open:    false,
  saving:  false,
  error:   '',
  reason:  '',
  payment: null as AdminManualPayment | null,
})

// ── Loaders ───────────────────────────────────────────────────────────────────
async function load() {
  loading.value = true
  try {
    const result = await adminService.getManualPayments({
      status: statusFilter.value || undefined,
      page:   page.value,
    })
    payments.value = result.data
    meta.value     = result.meta
  } finally {
    loading.value = false
  }
}

async function loadPendingCount() {
  const result = await adminService.getManualPayments({ status: 'pending', page: 1 })
  pendingCount.value = result.meta?.total ?? 0
}

// ── Actions ───────────────────────────────────────────────────────────────────
const actionError = ref('')   // affiche les erreurs d'approbation dans l'UI

const { confirm } = useConfirm()

async function doApprove(p: AdminManualPayment) {
  if (!(await confirm({
    title: t('admin.approvePayment'),
    message: t('admin.approveConfirm', { name: p.tenant_name, plan: p.plan_code }),
    confirmLabel: t('admin.approve'),
  }))) return
  actionError.value = ''
  try {
    await adminService.approveManualPayment(p.id)
  } catch (err: any) {
    actionError.value = err?.response?.data?.message ?? t('admin.approveError')
  } finally {
    // Toujours rafraîchir le tableau — succès ou échec
    await Promise.all([load(), loadPendingCount()])
  }
}

function openReject(p: AdminManualPayment) {
  rejectModal.payment = p
  rejectModal.reason  = ''
  rejectModal.error   = ''
  rejectModal.open    = true
}

async function confirmReject() {
  if (!rejectModal.reason) { rejectModal.error = t('admin.reasonRequired'); return }
  rejectModal.saving = true
  try {
    await adminService.rejectManualPayment(rejectModal.payment!.id, rejectModal.reason)
    rejectModal.open = false
    await Promise.all([load(), loadPendingCount()])
  } catch (err: any) {
    rejectModal.error = err?.response?.data?.message ?? t('common.genericError')
  } finally {
    rejectModal.saving = false
  }
}

// ── Helpers ───────────────────────────────────────────────────────────────────
const fmtDate = formatDate

const formatAmount = (cents: number, currency: string) => formatMoney(cents, currency)

function methodLabel(method: string): string {
  const m: Record<string, string> = {
    orange_money:   'Orange Money',
    wave:           'Wave',
    bank_transfer:  t('admin.bankTransfer'),
    mtn_money:      'MTN Money',
    moov_money:     'Moov Money',
  }
  return m[method] ?? method
}

function statusLabel(status: string): string {
  const m: Record<string, string> = {
    pending:  t('admin.paymentStatus.pending'),
    approved: t('admin.paymentStatus.approved'),
    rejected: t('admin.paymentStatus.rejected'),
  }
  return m[status] ?? status
}

onMounted(() => Promise.all([load(), loadPendingCount()]))
</script>

<style scoped>
.payment-review { display: flex; flex-direction: column; gap: 1rem; }

/* ── Action error banner ─────────────────────────────────────────────────── */
.action-error-banner {
  display: flex;
  align-items: center;
  gap: 0.625rem;
  background: #fef2f2;
  border: 1px solid #fecaca;
  border-radius: 8px;
  padding: 0.75rem 1rem;
  font-size: 0.8125rem;
  font-weight: 500;
  color: #991b1b;
}
.banner-close {
  margin-left: auto;
  background: none;
  border: none;
  color: #991b1b;
  cursor: pointer;
  font-size: 1rem;
  padding: 0 0.25rem;
  opacity: 0.7;
}
.banner-close:hover { opacity: 1; }

/* Fade transition for the banner */
.fade-enter-active { transition: opacity 0.2s ease; }
.fade-leave-active { transition: opacity 0.15s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }

/* ── Status tabs ─────────────────────────────────────────────────────────── */
.status-tabs { display: flex; gap: 0.25rem; border-bottom: 1px solid #e2e8f0; }
.status-tab {
  display: flex;
  align-items: center;
  gap: 0.375rem;
  padding: 0.5rem 1rem;
  border: none;
  background: none;
  font-size: 0.8125rem;
  font-weight: 500;
  color: #64748b;
  cursor: pointer;
  border-bottom: 2px solid transparent;
  margin-bottom: -1px;
  transition: color 0.15s, border-color 0.15s;
}
.status-tab.active   { color: #0f172a; border-bottom-color: #0f172a; }
.status-tab:hover:not(.active) { color: #334155; }
.tab-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 18px;
  height: 16px;
  padding: 0 4px;
  border-radius: 8px;
  font-size: 0.6875rem;
  font-weight: 700;
  background: #ef4444;
  color: white;
}

/* ── Table ───────────────────────────────────────────────────────────────── */
.table-wrap {
  background: white;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  overflow: auto;
}
.admin-table { width: 100%; border-collapse: collapse; font-size: 0.8125rem; }
.admin-table th {
  text-align: left;
  font-weight: 500;
  color: #64748b;
  font-size: 0.75rem;
  padding: 0.625rem 1rem;
  border-bottom: 1px solid #e2e8f0;
  white-space: nowrap;
}
.admin-table td {
  padding: 0.75rem 1rem;
  border-bottom: 1px solid #f8fafc;
  color: #334155;
  vertical-align: middle;
}
.admin-table tr:last-child td { border-bottom: none; }

.link-tenant { color: #1d4ed8; text-decoration: none; font-weight: 500; font-size: 0.8125rem; }
.link-tenant:hover { text-decoration: underline; }

.plan-chip { font-size: 0.6875rem; font-weight: 600; padding: 2px 8px; border-radius: 4px; background: #eff6ff; color: #1d4ed8; text-transform: uppercase; }
.bold  { font-weight: 700; color: #0f172a; }
.dim   { font-size: 0.8125rem; color: #64748b; }

.proof-link {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 0.75rem;
  color: #1d4ed8;
  text-decoration: none;
  max-width: 140px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.proof-link:hover { text-decoration: underline; }

.status-badge { font-size: 0.6875rem; font-weight: 600; padding: 2px 7px; border-radius: 4px; text-transform: uppercase; }
.status-badge--pending  { background: #fef9c3; color: #854d0e; }
.status-badge--approved { background: #dcfce7; color: #166534; }
.status-badge--rejected { background: #fef2f2; color: #991b1b; }

.rejection-reason { font-size: 0.6875rem; color: #94a3b8; margin-top: 3px; max-width: 200px; }

/* ── Action buttons ──────────────────────────────────────────────────────── */
.action-group { display: flex; gap: 0.375rem; }
.btn-sm { font-size: 0.75rem; font-weight: 500; padding: 3px 10px; border-radius: 5px; border: 1px solid #e2e8f0; background: white; cursor: pointer; color: #334155; transition: background 0.15s; }
.btn-sm--warn   { border-color: #fecaca; color: #ef4444; }
.btn-sm--warn:hover { background: #fff5f5; }
.btn-sm--ok     { border-color: #bbf7d0; color: #16a34a; }
.btn-sm--ok:hover { background: #f0fdf4; }

/* ── Pagination ──────────────────────────────────────────────────────────── */
.state-msg { padding: 2rem; text-align: center; color: #94a3b8; }
.pagination { display: flex; align-items: center; justify-content: center; gap: 1rem; font-size: 0.875rem; color: #475569; }
.pagination button { border: 1px solid #e2e8f0; background: white; border-radius: 7px; padding: 0.375rem 0.75rem; font-size: 0.8125rem; cursor: pointer; }
.pagination button:hover:not(:disabled) { background: #f8fafc; }
.pagination button:disabled { opacity: 0.4; cursor: not-allowed; }

/* ── Modal ───────────────────────────────────────────────────────────────── */
/* Modal chrome now provided by the shared <BaseModal> (UX-03). */
.modal-desc { font-size: 0.875rem; color: #475569; margin: 0 0 0.75rem; }

.form-textarea { width: 100%; border: 1px solid #e2e8f0; border-radius: 7px; padding: 0.5rem 0.75rem; font-size: 0.875rem; color: #0f172a; outline: none; resize: vertical; box-sizing: border-box; }
.form-textarea:focus { border-color: #94a3b8; }
.form-error { color: #ef4444; font-size: 0.8125rem; background: #fff5f5; border: 1px solid #fecaca; border-radius: 6px; padding: 0.5rem 0.75rem; margin-top: 0.5rem; }

.btn-cancel { padding: 0.5rem 1rem; border: 1px solid #e2e8f0; border-radius: 7px; background: white; color: #475569; font-size: 0.875rem; cursor: pointer; }
.btn-cancel:hover { background: #f8fafc; }
.btn-reject { padding: 0.5rem 1.25rem; border: none; border-radius: 7px; background: #ef4444; color: white; font-size: 0.875rem; font-weight: 600; cursor: pointer; }
.btn-reject:hover:not(:disabled) { opacity: 0.88; }
.btn-reject:disabled { opacity: 0.5; cursor: not-allowed; }
</style>
