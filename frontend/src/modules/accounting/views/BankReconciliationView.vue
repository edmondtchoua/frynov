<template>
  <div class="page-container">
    <AccountingTabNav />

    <div class="page-header">
      <div>
        <h1 class="page-title">{{ $t('accounting.bankRecTitle') }}</h1>
        <p class="page-subtitle">{{ $t('accounting.bankRecSubtitle') }}</p>
      </div>
    </div>

    <div class="filter-bar">
      <label class="filter-item">{{ $t('accounting.account') }}
        <select v-model="accountId" class="form-input" data-test="br-account" @change="load">
          <option value="">{{ $t('accounting.pickAccount') }}</option>
          <option v-for="a in bankAccounts" :key="a.id" :value="a.id">{{ a.code }} — {{ a.name }}</option>
        </select>
      </label>
      <label class="filter-item">{{ $t('accounting.statementBalance') }}
        <input v-model.number="statement" type="number" step="0.01" class="form-input" data-test="br-statement" @input="reloadState" />
      </label>
    </div>

    <div v-if="actionError" class="action-error" role="alert" data-test="br-error">
      {{ actionError }}<button class="action-error-close" @click="actionError = ''">×</button>
    </div>

    <template v-if="data">
      <div class="rec-summary card">
        <div><span>{{ $t('accounting.bookBalance') }}</span><strong data-test="br-book">{{ fmtSigned(data.summary.book_balance_minor) }}</strong></div>
        <div><span>{{ $t('accounting.statementBalance') }}</span><strong>{{ statement ? fmt(toCents(statement)) : '—' }}</strong></div>
        <div><span>{{ $t('accounting.depositsInTransit') }}</span><strong>{{ fmt(data.summary.outstanding_debit_minor) }}</strong></div>
        <div><span>{{ $t('accounting.outstandingChecks') }}</span><strong>{{ fmt(data.summary.outstanding_credit_minor) }}</strong></div>
        <div v-if="data.summary.difference_minor !== undefined" class="rec-diff" :class="data.summary.reconciled ? 'ok' : 'ko'" data-test="br-reconciled">
          <span>{{ $t('accounting.difference') }}</span>
          <strong>{{ fmtSigned(data.summary.difference_minor) }} {{ data.summary.reconciled ? '✓' : '⚠' }}</strong>
        </div>
      </div>

      <div class="card table-scroll">
        <StateBlock v-if="data.lines.length === 0" variant="empty" :title="$t('accounting.noMovements')" />
        <table v-else class="data-table">
          <thead>
            <tr>
              <th>{{ $t('accounting.pointed') }}</th>
              <th>{{ $t('accounting.colDate') }}</th>
              <th>{{ $t('accounting.colJournal') }}</th>
              <th>{{ $t('accounting.colNumber') }}</th>
              <th>{{ $t('accounting.colLabel') }}</th>
              <th class="num">{{ $t('accounting.debit') }}</th>
              <th class="num">{{ $t('accounting.credit') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="l in data.lines" :key="l.id" :class="{ pointed: l.pointed }" :data-test="`br-line-${l.id}`">
              <td><input type="checkbox" :checked="l.pointed" :data-test="`br-check-${l.id}`" @change="toggle(l)" /></td>
              <td>{{ fmtDate(l.date) }}</td>
              <td><code class="acc-code">{{ l.journal }}</code></td>
              <td>{{ l.number ?? '—' }}</td>
              <td>{{ l.label ?? '—' }}</td>
              <td class="num">{{ l.debit_minor ? fmt(l.debit_minor) : '—' }}</td>
              <td class="num">{{ l.credit_minor ? fmt(l.credit_minor) : '—' }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>
    <StateBlock v-else-if="loading" variant="loading" />
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import AccountingTabNav from '../components/AccountingTabNav.vue'
import StateBlock from '@/shared/ui/StateBlock.vue'
import { formatMoney } from '@/shared/utils/money'
import { formatDate } from '@/shared/utils/date'
import { useAuthStore } from '@/stores/auth'
import { accountingService } from '../services/accountingService'
import { t } from '@/i18n'
import type { Account, BankReconciliation, BankRecLine } from '../types'

const auth = useAuthStore()
const currency = computed(() => (auth.user as any)?.tenant?.settings?.currency ?? 'XOF')
const fmt = (c: number) => formatMoney(c ?? 0, currency.value)
const fmtSigned = (c: number) => (c < 0 ? '-' : '') + formatMoney(Math.abs(c ?? 0), currency.value)
const fmtDate = formatDate
const toCents = (v: number) => Math.round((v || 0) * 100)

const accounts    = ref<Account[]>([])
const accountId   = ref('')
const statement   = ref<number | null>(null)
const data        = ref<BankReconciliation | null>(null)
const loading     = ref(false)
const actionError = ref('')

// Comptes de trésorerie (classe 5) — banque, mobile money…
const bankAccounts = computed(() => accounts.value.filter(a => String(a.code).startsWith('5')))

async function reloadState() {
  if (!accountId.value) return
  data.value = await accountingService.bankReconciliation(accountId.value, statement.value != null ? toCents(statement.value) : undefined)
}

async function load() {
  data.value = null
  if (!accountId.value) return
  loading.value = true
  try { await reloadState() }
  finally { loading.value = false }
}

async function toggle(line: BankRecLine) {
  actionError.value = ''
  try {
    data.value = await accountingService.pointBankLines(accountId.value, [line.id], !line.pointed)
    // Recharge avec le solde de relevé pour rafraîchir l'écart.
    if (statement.value != null) await reloadState()
  } catch (e: any) {
    actionError.value = e?.response?.data?.message ?? t('accounting.pointFailed')
  }
}

onMounted(async () => {
  const res = await accountingService.accounts({ per_page: 200 })
  accounts.value = res.data
})
</script>

<style scoped>
.page-container { padding: 24px; max-width: 1100px; margin: 0 auto; }
.page-header { margin-bottom: 20px; }
.page-title { font-size: 1.5rem; font-weight: 700; margin: 0; }
.page-subtitle { color: var(--gray-500); margin: 4px 0 0; font-size: 0.875rem; }
.filter-bar { display: flex; gap: 20px; align-items: flex-end; margin-bottom: 16px; flex-wrap: wrap; }
.filter-item { display: flex; flex-direction: column; gap: 4px; font-size: .8rem; color: var(--gray-600); }
.filter-item .form-input { min-width: 240px; }
.rec-summary { display: flex; flex-wrap: wrap; gap: 24px; padding: 14px 18px; margin-bottom: 16px; }
.rec-summary > div { display: flex; flex-direction: column; gap: 2px; font-size: .85rem; }
.rec-summary span { color: var(--gray-500); font-size: .75rem; text-transform: uppercase; }
.rec-diff.ok strong { color: #15803d; }
.rec-diff.ko strong { color: #b91c1c; }
.num { text-align: right; }
.acc-code { background: var(--gray-100); padding: 1px 5px; border-radius: 4px; font-size: .78rem; }
tr.pointed { background: var(--gray-50); color: var(--gray-500); }
.action-error { display: flex; justify-content: space-between; align-items: center; gap: 12px; background: #fee2e2; color: #991b1b; border-radius: 8px; padding: 10px 14px; margin-bottom: 16px; font-size: 0.875rem; }
.action-error-close { border: none; background: transparent; color: inherit; font-size: 1.1rem; cursor: pointer; }
.table-scroll { overflow-x: auto; }
</style>
