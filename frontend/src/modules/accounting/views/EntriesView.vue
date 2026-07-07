<template>
  <div class="page-container">
    <AccountingTabNav />

    <div class="page-header">
      <div>
        <h1 class="page-title">{{ $t('accounting.entriesTitle') }}</h1>
        <p class="page-subtitle">{{ $t('accounting.entriesSubtitle') }}</p>
      </div>
      <button class="btn btn-primary" data-test="new-entry" @click="openCreate">{{ $t('accounting.newEntry') }}</button>
    </div>

    <div class="filter-bar">
      <select v-model="statusFilter" class="form-input filter-select" data-test="status-filter" @change="page = 1; load()">
        <option value="">{{ $t('accounting.allStatuses') }}</option>
        <option value="draft">{{ $t('accounting.entryStatus.draft') }}</option>
        <option value="posted">{{ $t('accounting.entryStatus.posted') }}</option>
        <option value="reversed">{{ $t('accounting.entryStatus.reversed') }}</option>
      </select>
    </div>

    <div v-if="actionError" class="action-error" role="alert" data-test="entry-error">
      {{ actionError }}<button class="action-error-close" @click="actionError = ''">×</button>
    </div>

    <div class="card table-scroll">
      <StateBlock v-if="loading" variant="loading" />
      <StateBlock v-else-if="entries.length === 0" variant="empty" :title="$t('accounting.noEntries')" />
      <table v-else class="data-table">
        <thead>
          <tr>
            <th>{{ $t('accounting.colNumber') }}</th>
            <th>{{ $t('accounting.colDate') }}</th>
            <th>{{ $t('accounting.colJournal') }}</th>
            <th>{{ $t('accounting.colLabel') }}</th>
            <th class="num">{{ $t('accounting.colAmount') }}</th>
            <th>{{ $t('accounting.colOrigin') }}</th>
            <th>{{ $t('common.status') }}</th>
            <th>{{ $t('common.actions') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="e in entries" :key="e.id" :data-test="`entry-${e.id}`">
            <td><code class="acc-code">{{ e.number ?? '—' }}</code></td>
            <td>{{ fmtDate(e.entry_date) }}</td>
            <td>{{ e.journal?.code }}</td>
            <td>{{ e.label }}</td>
            <td class="num">{{ fmt(total(e)) }}</td>
            <td><span v-if="e.rule_code" class="badge badge-gray acc-origin">{{ e.rule_code }}</span><span v-else class="text-muted">{{ $t('accounting.manual') }}</span></td>
            <td><span :class="statusBadge(e.status)">{{ $t('accounting.entryStatus.' + e.status) }}</span></td>
            <td class="actions-cell">
              <button v-if="e.status === 'draft'" class="btn btn-sm btn-primary" :data-test="`post-${e.id}`" @click="post(e)">{{ $t('accounting.post') }}</button>
              <button v-else-if="e.status === 'posted' && !e.reversed_by_id" class="btn btn-sm btn-ghost" :data-test="`reverse-${e.id}`" @click="openReverse(e)">{{ $t('accounting.reverse') }}</button>
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

    <!-- Nouvelle écriture manuelle -->
    <BaseModal v-model="createModal.open" :title="$t('accounting.newEntry')">
      <div class="form-row">
        <div class="form-group" style="flex:1">
          <label class="form-label">{{ $t('accounting.colJournal') }} *</label>
          <select v-model="createModal.journal_id" class="form-input" data-test="entry-journal">
            <option v-for="j in journals" :key="j.id" :value="j.id">{{ j.code }} — {{ j.name }}</option>
          </select>
        </div>
        <div class="form-group" style="flex:1">
          <label class="form-label">{{ $t('accounting.colDate') }} *</label>
          <input v-model="createModal.date" type="date" class="form-input" data-test="entry-date" />
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">{{ $t('accounting.colLabel') }} *</label>
        <input v-model="createModal.label" class="form-input" data-test="entry-label" />
      </div>

      <table class="entry-lines">
        <thead>
          <tr>
            <th>{{ $t('accounting.account') }}</th>
            <th class="num">{{ $t('accounting.debit') }}</th>
            <th class="num">{{ $t('accounting.credit') }}</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(l, i) in createModal.lines" :key="i" :data-test="`line-${i}`">
            <td>
              <select v-model="l.account_id" class="form-input form-input-sm" :data-test="`line-account-${i}`">
                <option value="">—</option>
                <option v-for="a in accounts" :key="a.id" :value="a.id">{{ a.code }} · {{ a.name }}</option>
              </select>
            </td>
            <td><input v-model.number="l.debit" type="number" min="0" step="0.01" class="form-input form-input-sm num" :data-test="`line-debit-${i}`" @input="l.credit = 0" /></td>
            <td><input v-model.number="l.credit" type="number" min="0" step="0.01" class="form-input form-input-sm num" :data-test="`line-credit-${i}`" @input="l.debit = 0" /></td>
            <td><button class="btn-icon-sm" :disabled="createModal.lines.length <= 2" @click="createModal.lines.splice(i, 1)">×</button></td>
          </tr>
        </tbody>
        <tfoot>
          <tr>
            <td><button class="btn btn-ghost btn-sm" data-test="add-line" @click="addLine">+ {{ $t('accounting.addLine') }}</button></td>
            <td class="num"><strong>{{ fmt(totalDebit) }}</strong></td>
            <td class="num"><strong>{{ fmt(totalCredit) }}</strong></td>
            <td></td>
          </tr>
        </tfoot>
      </table>
      <p class="acc-balance" :class="{ 'acc-balance--ok': balanced, 'acc-balance--off': !balanced }" data-test="balance">
        {{ balanced ? $t('accounting.balanced') : $t('accounting.unbalanced', { diff: fmt(Math.abs(totalDebit - totalCredit)) }) }}
      </p>
      <p v-if="createModal.error" class="form-error" data-test="create-error">{{ createModal.error }}</p>
      <template #footer>
        <button class="btn btn-ghost" @click="createModal.open = false">{{ $t('common.cancel') }}</button>
        <button class="btn btn-primary" :disabled="createModal.saving || !canCreate" data-test="create-submit" @click="submitCreate">{{ $t('common.save') }}</button>
      </template>
    </BaseModal>

    <!-- Extourne -->
    <BaseModal v-model="reverseModal.open" :title="$t('accounting.reverseTitle', { number: reverseModal.number })" size="sm">
      <p class="acc-hint">{{ $t('accounting.reverseDesc') }}</p>
      <div class="form-group">
        <label class="form-label">{{ $t('accounting.reverseReason') }}</label>
        <input v-model="reverseModal.reason" class="form-input" data-test="reverse-reason" />
      </div>
      <p v-if="reverseModal.error" class="form-error">{{ reverseModal.error }}</p>
      <template #footer>
        <button class="btn btn-ghost" @click="reverseModal.open = false">{{ $t('common.cancel') }}</button>
        <button class="btn btn-primary" :disabled="reverseModal.saving" data-test="reverse-submit" @click="submitReverse">{{ $t('accounting.reverse') }}</button>
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
import type { Account, Entry, Journal } from '../types'

const auth = useAuthStore()
const currency = computed(() => (auth.user as any)?.tenant?.settings?.currency ?? 'XOF')
const fmt = (cents: number) => formatMoney(cents ?? 0, currency.value)
const fmtDate = formatDate

const entries      = ref<Entry[]>([])
const journals     = ref<Journal[]>([])
const accounts     = ref<Account[]>([])
const loading      = ref(false)
const statusFilter = ref('')
const page         = ref(1)
const meta         = ref({ current_page: 1, last_page: 1, total: 0 })
const actionError  = ref('')

const total = (e: Entry) => e.lines.reduce((s, l) => s + l.debit_minor, 0)
function statusBadge(s: string) {
  return 'badge ' + ({ draft: 'badge-gray', posted: 'badge-success', reversed: 'badge-warning' }[s] ?? 'badge-gray')
}

async function load() {
  loading.value = true
  try {
    const res = await accountingService.entries({ status: statusFilter.value || undefined, page: page.value, per_page: 30 })
    entries.value = res.data
    meta.value = res.meta
  } finally {
    loading.value = false
  }
}

async function post(e: Entry) {
  actionError.value = ''
  try { await accountingService.postEntry(e.id); await load() }
  catch (err: any) { actionError.value = err?.response?.data?.message ?? t('accounting.postFailed') }
}

// ── Création ────────────────────────────────────────────────────────────────
const createModal = reactive<{ open: boolean; saving: boolean; error: string; journal_id: string; date: string; label: string; lines: { account_id: string; debit: number; credit: number }[] }>({
  open: false, saving: false, error: '', journal_id: '', date: '', label: '', lines: [],
})

const toCents = (v: number) => Math.round((v || 0) * 100)
const totalDebit  = computed(() => createModal.lines.reduce((s, l) => s + toCents(l.debit), 0))
const totalCredit = computed(() => createModal.lines.reduce((s, l) => s + toCents(l.credit), 0))
const balanced    = computed(() => totalDebit.value > 0 && totalDebit.value === totalCredit.value)
const canCreate   = computed(() => balanced.value && !!createModal.journal_id && !!createModal.label.trim() && createModal.lines.every(l => l.account_id))

function addLine() { createModal.lines.push({ account_id: '', debit: 0, credit: 0 }) }

async function openCreate() {
  if (journals.value.length === 0) journals.value = await accountingService.journals()
  if (accounts.value.length === 0) accounts.value = (await accountingService.accounts({ per_page: 200 })).data
  Object.assign(createModal, {
    open: true, saving: false, error: '',
    journal_id: journals.value.find(j => j.code === 'OD')?.id ?? journals.value[0]?.id ?? '',
    date: new Date().toISOString().slice(0, 10), label: '',
    lines: [{ account_id: '', debit: 0, credit: 0 }, { account_id: '', debit: 0, credit: 0 }],
  })
}

async function submitCreate() {
  createModal.saving = true
  createModal.error = ''
  try {
    await accountingService.createEntry({
      journal_id: createModal.journal_id,
      entry_date: createModal.date,
      label: createModal.label.trim(),
      lines: createModal.lines.map(l => ({ account_id: l.account_id, debit_minor: toCents(l.debit), credit_minor: toCents(l.credit) })),
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

// ── Extourne ────────────────────────────────────────────────────────────────
const reverseModal = reactive({ open: false, saving: false, error: '', id: '', number: '', reason: '' })

function openReverse(e: Entry) {
  Object.assign(reverseModal, { open: true, saving: false, error: '', id: e.id, number: e.number ?? '', reason: '' })
}

async function submitReverse() {
  reverseModal.saving = true
  reverseModal.error = ''
  try {
    await accountingService.reverseEntry(reverseModal.id, reverseModal.reason || undefined)
    reverseModal.open = false
    await load()
  } catch (e: any) {
    reverseModal.error = e?.response?.data?.message ?? t('accounting.reverseFailed')
  } finally {
    reverseModal.saving = false
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
.acc-origin { font-size: .68rem; font-family: monospace; }
.form-row { display: flex; gap: 12px; }
.entry-lines { width: 100%; border-collapse: collapse; margin-top: 8px; }
.entry-lines th { font-size: .72rem; color: var(--gray-500); text-transform: uppercase; padding: 4px 6px; text-align: left; }
.entry-lines td { padding: 3px 6px; }
.form-input-sm { padding: 5px 8px; font-size: .85rem; }
.acc-balance { text-align: right; font-weight: 600; font-size: .88rem; margin: 8px 0 0; }
.acc-balance--ok { color: #059669; }
.acc-balance--off { color: #b45309; }
.acc-hint { font-size: .82rem; color: var(--gray-500); }
.action-error { display: flex; justify-content: space-between; align-items: center; gap: 12px; background: #fee2e2; color: #991b1b; border-radius: 8px; padding: 10px 14px; margin-bottom: 16px; font-size: 0.875rem; }
.action-error-close { border: none; background: transparent; color: inherit; font-size: 1.1rem; cursor: pointer; }
.form-error { color: #dc2626; font-size: .85rem; }
.pagination { display: flex; align-items: center; justify-content: center; gap: 1rem; margin-top: 1.5rem; font-size: .875rem; color: #6b7280; }
.btn-icon-sm { border: none; background: transparent; color: #dc2626; cursor: pointer; font-size: 1.1rem; }
.table-scroll { overflow-x: auto; }
.text-muted { color: var(--gray-400); }
</style>
