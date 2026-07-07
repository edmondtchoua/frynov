<template>
  <div class="page-container">
    <AccountingTabNav />

    <div class="page-header">
      <div>
        <h1 class="page-title">{{ $t('accounting.invoicesTitle') }}</h1>
        <p class="page-subtitle">{{ $t('accounting.invoicesSubtitle') }}</p>
      </div>
      <button class="btn btn-primary" data-test="new-invoice" @click="openCreate">{{ $t('accounting.newInvoice') }}</button>
    </div>

    <div class="filter-bar">
      <select v-model="statusFilter" class="form-input filter-select" data-test="status-filter" @change="page = 1; load()">
        <option value="">{{ $t('accounting.allStatuses') }}</option>
        <option value="draft">{{ $t('accounting.invStatus.draft') }}</option>
        <option value="issued">{{ $t('accounting.invStatus.issued') }}</option>
        <option value="partially_paid">{{ $t('accounting.invStatus.partially_paid') }}</option>
        <option value="paid">{{ $t('accounting.invStatus.paid') }}</option>
      </select>
    </div>

    <div v-if="actionError" class="action-error" role="alert" data-test="invoice-error">
      {{ actionError }}<button class="action-error-close" @click="actionError = ''">×</button>
    </div>

    <div class="card table-scroll">
      <StateBlock v-if="loading" variant="loading" />
      <StateBlock v-else-if="invoices.length === 0" variant="empty" :title="$t('accounting.noInvoices')" />
      <table v-else class="data-table">
        <thead>
          <tr>
            <th>{{ $t('accounting.colNumber') }}</th>
            <th>{{ $t('accounting.customer') }}</th>
            <th>{{ $t('accounting.colDate') }}</th>
            <th class="num">{{ $t('accounting.totalHt') }}</th>
            <th class="num">{{ $t('accounting.totalTtc') }}</th>
            <th class="num">{{ $t('accounting.paid') }}</th>
            <th>{{ $t('common.status') }}</th>
            <th>{{ $t('common.actions') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="inv in invoices" :key="inv.id" :data-test="`invoice-${inv.id}`">
            <td><code class="acc-code">{{ inv.number ?? $t('accounting.draftShort') }}</code></td>
            <td>{{ inv.customer_name ?? '—' }}</td>
            <td>{{ inv.issue_date ? fmtDate(inv.issue_date) : '—' }}</td>
            <td class="num">{{ fmt(inv.subtotal_minor) }}</td>
            <td class="num">{{ fmt(inv.total_minor) }}</td>
            <td class="num">{{ fmt(inv.paid_minor) }}</td>
            <td><span :class="statusBadge(inv.status)">{{ $t('accounting.invStatus.' + inv.status) }}</span></td>
            <td class="actions-cell">
              <button v-if="inv.status === 'draft'" class="btn btn-sm btn-primary" :data-test="`issue-${inv.id}`" @click="issue(inv)">{{ $t('accounting.issue') }}</button>
              <template v-else>
                <button v-if="inv.status !== 'paid'" class="btn btn-sm btn-ghost" :data-test="`pay-${inv.id}`" @click="openPay(inv)">{{ $t('accounting.recordPayment') }}</button>
                <a class="btn btn-sm btn-ghost" :href="pdfUrl(inv.id)" target="_blank" rel="noopener" :data-test="`pdf-${inv.id}`">PDF</a>
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

    <!-- Nouvelle facture -->
    <BaseModal v-model="createModal.open" :title="$t('accounting.newInvoice')">
      <div class="form-row">
        <div class="form-group" style="flex:2">
          <label class="form-label">{{ $t('accounting.customer') }}</label>
          <input v-model="createModal.customer" class="form-input" data-test="inv-customer" />
        </div>
        <div class="form-group" style="flex:1">
          <label class="form-label">{{ $t('accounting.dueDate') }}</label>
          <input v-model="createModal.due" type="date" class="form-input" data-test="inv-due" />
        </div>
      </div>

      <table class="inv-lines">
        <thead>
          <tr>
            <th>{{ $t('accounting.designation') }}</th>
            <th class="num">{{ $t('accounting.qty') }}</th>
            <th class="num">{{ $t('accounting.unitPrice') }}</th>
            <th class="num">{{ $t('accounting.discountPct') }}</th>
            <th>{{ $t('accounting.tax') }}</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(l, i) in createModal.lines" :key="i" :data-test="`inv-line-${i}`">
            <td><input v-model="l.label" class="form-input form-input-sm" :data-test="`inv-label-${i}`" /></td>
            <td><input v-model.number="l.quantity" type="number" min="1" class="form-input form-input-sm num" style="width:60px" /></td>
            <td><input v-model.number="l.price" type="number" min="0" step="0.01" class="form-input form-input-sm num" style="width:100px" :data-test="`inv-price-${i}`" /></td>
            <td><input v-model.number="l.discountPct" type="number" min="0" max="100" step="0.1" class="form-input form-input-sm num" style="width:70px" /></td>
            <td>
              <select v-model="l.tax_id" class="form-input form-input-sm">
                <option value="">{{ $t('accounting.noTax') }}</option>
                <option v-for="tx in taxes" :key="tx.id" :value="tx.id">{{ tx.code }}</option>
              </select>
            </td>
            <td><button class="btn-icon-sm" :disabled="createModal.lines.length <= 1" @click="createModal.lines.splice(i, 1)">×</button></td>
          </tr>
        </tbody>
      </table>
      <button class="btn btn-ghost btn-sm" data-test="inv-add-line" @click="addLine">+ {{ $t('accounting.addLine') }}</button>

      <div class="inv-totals">
        <div><span>{{ $t('accounting.totalHt') }}</span><strong data-test="inv-subtotal">{{ fmt(subtotal) }}</strong></div>
        <div><span>{{ $t('accounting.vat') }}</span><strong>{{ fmt(taxTotal) }}</strong></div>
        <div class="inv-grand"><span>{{ $t('accounting.totalTtc') }}</span><strong data-test="inv-total">{{ fmt(total) }}</strong></div>
      </div>
      <p v-if="createModal.error" class="form-error" data-test="create-error">{{ createModal.error }}</p>
      <template #footer>
        <button class="btn btn-ghost" @click="createModal.open = false">{{ $t('common.cancel') }}</button>
        <button class="btn btn-primary" :disabled="createModal.saving || !canCreate" data-test="inv-submit" @click="submitCreate">{{ $t('accounting.saveDraft') }}</button>
      </template>
    </BaseModal>

    <!-- Paiement -->
    <BaseModal v-model="payModal.open" :title="$t('accounting.recordPaymentTitle', { number: payModal.number })" size="sm">
      <p class="acc-hint">{{ $t('accounting.remaining') }} : <strong>{{ fmt(payModal.remaining) }}</strong></p>
      <div class="form-group">
        <label class="form-label">{{ $t('accounting.paymentId') }}</label>
        <input v-model="payModal.paymentId" class="form-input" :placeholder="$t('accounting.paymentIdHint')" data-test="pay-payment-id" />
      </div>
      <div class="form-group">
        <label class="form-label">{{ $t('common.amount') }}</label>
        <input v-model.number="payModal.amount" type="number" min="0" step="0.01" class="form-input" data-test="pay-amount" />
      </div>
      <p v-if="payModal.error" class="form-error">{{ payModal.error }}</p>
      <template #footer>
        <button class="btn btn-ghost" @click="payModal.open = false">{{ $t('common.cancel') }}</button>
        <button class="btn btn-primary" :disabled="!payModal.paymentId || !payModal.amount" data-test="pay-submit" @click="submitPay">{{ $t('common.save') }}</button>
      </template>
    </BaseModal>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import AccountingTabNav from '../components/AccountingTabNav.vue'
import StateBlock from '@/shared/ui/StateBlock.vue'
import BaseModal from '@/shared/ui/BaseModal.vue'
import { formatMoney } from '@/shared/utils/money'
import { formatDate } from '@/shared/utils/date'
import { useAuthStore } from '@/stores/auth'
import { accountingService } from '../services/accountingService'
import { t } from '@/i18n'
import type { Invoice, Tax } from '../types'

const auth = useAuthStore()
const currency = computed(() => (auth.user as any)?.tenant?.settings?.currency ?? 'XOF')
const fmt = (cents: number) => formatMoney(cents ?? 0, currency.value)
const fmtDate = formatDate

const invoices     = ref<Invoice[]>([])
const taxes        = ref<Tax[]>([])
const loading      = ref(false)
const statusFilter = ref('')
const page         = ref(1)
const meta         = ref({ current_page: 1, last_page: 1, total: 0 })
const actionError  = ref('')

const pdfUrl = (id: string) => accountingService.invoicePdfUrl(id)
function statusBadge(s: string) {
  return 'badge ' + ({ draft: 'badge-gray', issued: 'badge-blue', partially_paid: 'badge-warning', paid: 'badge-success', cancelled: 'badge-gray' }[s] ?? 'badge-gray')
}

async function load() {
  loading.value = true
  try {
    const res = await accountingService.invoices({ status: statusFilter.value || undefined, page: page.value, per_page: 30 })
    invoices.value = res.data
    meta.value = res.meta
  } finally {
    loading.value = false
  }
}

async function issue(inv: Invoice) {
  actionError.value = ''
  try { await accountingService.issueInvoice(inv.id); await load() }
  catch (e: any) { actionError.value = e?.response?.data?.message ?? t('accounting.issueFailed') }
}

// ── Création ────────────────────────────────────────────────────────────────
const createModal = reactive<{ open: boolean; saving: boolean; error: string; customer: string; due: string; lines: { label: string; quantity: number; price: number; discountPct: number; tax_id: string }[] }>({
  open: false, saving: false, error: '', customer: '', due: '', lines: [],
})

const toCents = (v: number) => Math.round((v || 0) * 100)
function lineHt(l: { quantity: number; price: number; discountPct: number }) {
  const base = (l.quantity || 0) * toCents(l.price)
  return base - Math.round(base * ((l.discountPct || 0) / 100))
}
function lineTax(l: { quantity: number; price: number; discountPct: number; tax_id: string }) {
  const tx = taxes.value.find(t => t.id === l.tax_id)
  return tx ? Math.round((lineHt(l) * tx.rate_bp + 5000) / 10000) : 0
}
const subtotal = computed(() => createModal.lines.reduce((s, l) => s + lineHt(l), 0))
const taxTotal = computed(() => createModal.lines.reduce((s, l) => s + lineTax(l), 0))
const total    = computed(() => subtotal.value + taxTotal.value)
const canCreate = computed(() => total.value > 0 && createModal.lines.every(l => l.label.trim() && l.quantity > 0))

function addLine() { createModal.lines.push({ label: '', quantity: 1, price: 0, discountPct: 0, tax_id: '' }) }

async function openCreate() {
  if (taxes.value.length === 0) taxes.value = await accountingService.taxes()
  Object.assign(createModal, { open: true, saving: false, error: '', customer: '', due: '', lines: [{ label: '', quantity: 1, price: 0, discountPct: 0, tax_id: '' }] })
}

async function submitCreate() {
  createModal.saving = true
  createModal.error = ''
  try {
    await accountingService.createInvoice({
      customer_name: createModal.customer.trim() || undefined,
      due_date: createModal.due || undefined,
      lines: createModal.lines.map(l => ({
        label: l.label.trim(), quantity: l.quantity, unit_price_minor: toCents(l.price),
        discount_bp: Math.round((l.discountPct || 0) * 100), tax_id: l.tax_id || undefined,
      })),
    })
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

// ── Paiement ────────────────────────────────────────────────────────────────
const payModal = reactive({ open: false, error: '', id: '', number: '', remaining: 0, paymentId: '', amount: 0 })

function openPay(inv: Invoice) {
  const remaining = inv.total_minor - inv.paid_minor
  Object.assign(payModal, { open: true, error: '', id: inv.id, number: inv.number ?? '', remaining, paymentId: '', amount: remaining / 100 })
}

async function submitPay() {
  payModal.error = ''
  try {
    await accountingService.allocatePayment(payModal.id, { payment_id: payModal.paymentId.trim(), amount_minor: toCents(payModal.amount) })
    payModal.open = false
    await load()
  } catch (e: any) {
    payModal.error = e?.response?.data?.message
      ?? (Object.values(e?.response?.data?.errors ?? {}).flat()[0] as string)
      ?? t('accounting.payFailed')
  }
}

onMounted(load)
</script>

<style scoped>
.page-container { padding: 24px; max-width: 1100px; margin: 0 auto; }
.page-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; }
.page-title { font-size: 1.5rem; font-weight: 700; margin: 0; }
.page-subtitle { color: var(--gray-500); margin: 4px 0 0; font-size: 0.875rem; }
.filter-bar { margin-bottom: 16px; }
.filter-select { max-width: 220px; }
.num { text-align: right; }
.acc-code { background: var(--gray-100); padding: 2px 6px; border-radius: 4px; font-size: .8rem; }
.form-row { display: flex; gap: 12px; }
.inv-lines { width: 100%; border-collapse: collapse; margin-top: 8px; }
.inv-lines th { font-size: .72rem; color: var(--gray-500); text-transform: uppercase; padding: 4px 6px; text-align: left; }
.inv-lines td { padding: 3px 4px; }
.form-input-sm { padding: 5px 8px; font-size: .85rem; }
.inv-totals { margin-top: 12px; margin-left: auto; width: 260px; }
.inv-totals > div { display: flex; justify-content: space-between; padding: 3px 0; font-size: .9rem; }
.inv-grand { border-top: 2px solid var(--gray-300); margin-top: 4px; padding-top: 6px; font-weight: 700; font-size: 1rem; }
.acc-hint { font-size: .85rem; color: var(--gray-600); }
.action-error { display: flex; justify-content: space-between; align-items: center; gap: 12px; background: #fee2e2; color: #991b1b; border-radius: 8px; padding: 10px 14px; margin-bottom: 16px; font-size: 0.875rem; }
.action-error-close { border: none; background: transparent; color: inherit; font-size: 1.1rem; cursor: pointer; }
.form-error { color: #dc2626; font-size: .85rem; }
.pagination { display: flex; align-items: center; justify-content: center; gap: 1rem; margin-top: 1.5rem; font-size: .875rem; color: #6b7280; }
.btn-icon-sm { border: none; background: transparent; color: #dc2626; cursor: pointer; font-size: 1.1rem; }
.table-scroll { overflow-x: auto; }
</style>
