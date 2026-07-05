<template>
  <div class="page-container">
    <AccountingTabNav />

    <div class="page-header">
      <div>
        <h1 class="page-title">{{ $t('accounting.creditNotesTitle') }}</h1>
        <p class="page-subtitle">{{ $t('accounting.creditNotesSubtitle') }}</p>
      </div>
      <button class="btn btn-primary" data-test="new-credit-note" @click="openCreate">{{ $t('accounting.newCreditNote') }}</button>
    </div>

    <div v-if="actionError" class="action-error" role="alert" data-test="cn-error">
      {{ actionError }}<button class="action-error-close" @click="actionError = ''">×</button>
    </div>

    <div class="card table-scroll">
      <StateBlock v-if="loading" variant="loading" />
      <StateBlock v-else-if="creditNotes.length === 0" variant="empty" :title="$t('accounting.noCreditNotes')" />
      <table v-else class="data-table">
        <thead>
          <tr>
            <th>{{ $t('accounting.colNumber') }}</th>
            <th>{{ $t('accounting.customer') }}</th>
            <th>{{ $t('accounting.colDate') }}</th>
            <th class="num">{{ $t('accounting.totalHt') }}</th>
            <th class="num">{{ $t('accounting.totalTtc') }}</th>
            <th class="num">{{ $t('accounting.applied') }}</th>
            <th>{{ $t('common.status') }}</th>
            <th>{{ $t('common.actions') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="cn in creditNotes" :key="cn.id" :data-test="`cn-${cn.id}`">
            <td><code class="acc-code">{{ cn.number ?? $t('accounting.draftShort') }}</code></td>
            <td>{{ cn.customer_name ?? '—' }}</td>
            <td>{{ cn.issue_date ? fmtDate(cn.issue_date) : '—' }}</td>
            <td class="num">{{ fmt(cn.subtotal_minor) }}</td>
            <td class="num">{{ fmt(cn.total_minor) }}</td>
            <td class="num">{{ fmt(cn.paid_minor) }}</td>
            <td><span :class="statusBadge(cn.status)">{{ $t('accounting.cnStatus.' + cn.status) }}</span></td>
            <td class="actions-cell">
              <button v-if="cn.status === 'draft'" class="btn btn-sm btn-primary" :data-test="`issue-cn-${cn.id}`" @click="issue(cn)">{{ $t('accounting.issue') }}</button>
              <template v-else>
                <button v-if="cn.status !== 'paid'" class="btn btn-sm btn-ghost" :data-test="`apply-${cn.id}`" @click="openApply(cn)">{{ $t('accounting.applyCn') }}</button>
                <a class="btn btn-sm btn-ghost" :href="pdfUrl(cn.id)" target="_blank" rel="noopener" :data-test="`pdf-cn-${cn.id}`">PDF</a>
              </template>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="meta.last_page > 1" class="pagination">
      <button class="btn btn-secondary" :disabled="page <= 1" @click="page--; load()">‹ {{ $t('common.previous') }}</button>
      <span>{{ $t('common.pageOf', { current: meta.current_page, total: meta.last_page }) }}</span>
      <button class="btn btn-secondary" :disabled="page >= meta.last_page" @click="page++; load()">{{ $t('common.next') }} ›</button>
    </div>

    <!-- Nouvel avoir : depuis une facture émise -->
    <BaseModal v-model="createModal.open" :title="$t('accounting.newCreditNote')" size="sm">
      <p class="acc-hint">{{ $t('accounting.creditNoteFromHint') }}</p>
      <div class="form-group">
        <label class="form-label">{{ $t('accounting.sourceInvoice') }}</label>
        <select v-model="createModal.invoiceId" class="form-input" data-test="cn-source">
          <option value="">{{ $t('accounting.pickInvoice') }}</option>
          <option v-for="inv in sourceInvoices" :key="inv.id" :value="inv.id">
            {{ inv.number }} — {{ inv.customer_name ?? '—' }} ({{ fmt(inv.total_minor) }})
          </option>
        </select>
      </div>
      <p v-if="createModal.error" class="form-error" data-test="cn-create-error">{{ createModal.error }}</p>
      <template #footer>
        <button class="btn btn-ghost" @click="createModal.open = false">{{ $t('common.cancel') }}</button>
        <button class="btn btn-primary" :disabled="createModal.saving || !createModal.invoiceId" data-test="cn-submit" @click="submitCreate">{{ $t('accounting.saveDraft') }}</button>
      </template>
    </BaseModal>

    <!-- Appliquer l'avoir à une facture -->
    <BaseModal v-model="applyModal.open" :title="$t('accounting.applyTitle', { number: applyModal.number })" size="sm">
      <p class="acc-hint">{{ $t('accounting.remainingToApply') }} : <strong>{{ fmt(applyModal.remaining) }}</strong></p>
      <div class="form-group">
        <label class="form-label">{{ $t('accounting.targetInvoice') }}</label>
        <input v-model="applyModal.invoiceId" class="form-input" :placeholder="$t('accounting.invoiceIdHint')" data-test="apply-invoice-id" />
      </div>
      <div class="form-group">
        <label class="form-label">{{ $t('common.amount') }}</label>
        <input v-model.number="applyModal.amount" type="number" min="0" step="0.01" class="form-input" data-test="apply-amount" />
      </div>
      <p v-if="applyModal.error" class="form-error" data-test="apply-error">{{ applyModal.error }}</p>
      <template #footer>
        <button class="btn btn-ghost" @click="applyModal.open = false">{{ $t('common.cancel') }}</button>
        <button class="btn btn-primary" :disabled="!applyModal.invoiceId || !applyModal.amount" data-test="apply-submit" @click="submitApply">{{ $t('common.save') }}</button>
      </template>
    </BaseModal>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { computed } from 'vue'
import AccountingTabNav from '../components/AccountingTabNav.vue'
import StateBlock from '@/shared/ui/StateBlock.vue'
import BaseModal from '@/shared/ui/BaseModal.vue'
import { formatMoney } from '@/shared/utils/money'
import { formatDate } from '@/shared/utils/date'
import { useAuthStore } from '@/stores/auth'
import { accountingService } from '../services/accountingService'
import { t } from '@/i18n'
import type { Invoice } from '../types'

const auth = useAuthStore()
const currency = computed(() => (auth.user as any)?.tenant?.settings?.currency ?? 'XOF')
const fmt = (cents: number) => formatMoney(cents ?? 0, currency.value)
const fmtDate = formatDate

const creditNotes = ref<Invoice[]>([])
const loading     = ref(false)
const page        = ref(1)
const meta        = ref({ current_page: 1, last_page: 1, total: 0 })
const actionError = ref('')

const toCents = (v: number) => Math.round((v || 0) * 100)
const pdfUrl = (id: string) => accountingService.invoicePdfUrl(id)
function statusBadge(s: string) {
  return 'badge ' + ({ draft: 'badge-gray', issued: 'badge-blue', partially_paid: 'badge-warning', paid: 'badge-success', cancelled: 'badge-gray' }[s] ?? 'badge-gray')
}

async function load() {
  loading.value = true
  try {
    const res = await accountingService.creditNotes({ page: page.value, per_page: 30 })
    creditNotes.value = res.data
    meta.value = res.meta
  } finally {
    loading.value = false
  }
}

async function issue(cn: Invoice) {
  actionError.value = ''
  try { await accountingService.issueCreditNote(cn.id); await load() }
  catch (e: any) { actionError.value = e?.response?.data?.message ?? t('accounting.issueFailed') }
}

// ── Création (depuis une facture émise) ───────────────────────────────────────
const sourceInvoices = ref<Invoice[]>([])
const createModal = reactive({ open: false, saving: false, error: '', invoiceId: '' })

async function openCreate() {
  createModal.error = ''
  createModal.invoiceId = ''
  // Factures émises (non brouillon) éligibles à un avoir.
  const [issued, partial] = await Promise.all([
    accountingService.invoices({ status: 'issued', per_page: 100 }),
    accountingService.invoices({ status: 'partially_paid', per_page: 100 }),
  ])
  sourceInvoices.value = [...issued.data, ...partial.data]
  createModal.open = true
}

async function submitCreate() {
  createModal.saving = true
  createModal.error = ''
  try {
    await accountingService.createCreditNote(createModal.invoiceId)
    createModal.open = false
    await load()
  } catch (e: any) {
    createModal.error = e?.response?.data?.message
      ?? (Object.values(e?.response?.data?.errors ?? {}).flat()[0] as string)
      ?? t('accounting.createError')
  } finally {
    createModal.saving = false
  }
}

// ── Application ──────────────────────────────────────────────────────────────
const applyModal = reactive({ open: false, error: '', id: '', number: '', remaining: 0, invoiceId: '', amount: 0 })

function openApply(cn: Invoice) {
  const remaining = cn.total_minor - cn.paid_minor
  Object.assign(applyModal, {
    open: true, error: '', id: cn.id, number: cn.number ?? '', remaining,
    invoiceId: cn.credit_note_of_id ?? '', amount: remaining / 100,
  })
}

async function submitApply() {
  applyModal.error = ''
  try {
    await accountingService.applyCreditNote(applyModal.id, { invoice_id: applyModal.invoiceId.trim(), amount_minor: toCents(applyModal.amount) })
    applyModal.open = false
    await load()
  } catch (e: any) {
    applyModal.error = e?.response?.data?.message
      ?? (Object.values(e?.response?.data?.errors ?? {}).flat()[0] as string)
      ?? t('accounting.applyFailed')
  }
}

onMounted(load)
</script>

<style scoped>
.page-container { padding: 24px; max-width: 1100px; margin: 0 auto; }
.page-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; }
.page-title { font-size: 1.5rem; font-weight: 700; margin: 0; }
.page-subtitle { color: var(--gray-500); margin: 4px 0 0; font-size: 0.875rem; }
.num { text-align: right; }
.acc-code { background: var(--gray-100); padding: 2px 6px; border-radius: 4px; font-size: .8rem; }
.acc-hint { font-size: .85rem; color: var(--gray-600); margin-bottom: 8px; }
.action-error { display: flex; justify-content: space-between; align-items: center; gap: 12px; background: #fee2e2; color: #991b1b; border-radius: 8px; padding: 10px 14px; margin-bottom: 16px; font-size: 0.875rem; }
.action-error-close { border: none; background: transparent; color: inherit; font-size: 1.1rem; cursor: pointer; }
.form-error { color: #dc2626; font-size: .85rem; }
.pagination { display: flex; align-items: center; justify-content: center; gap: 1rem; margin-top: 1.5rem; font-size: .875rem; color: #6b7280; }
.table-scroll { overflow-x: auto; }
</style>
