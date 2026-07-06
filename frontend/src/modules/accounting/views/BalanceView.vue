<template>
  <div class="page-container">
    <AccountingTabNav />

    <div class="page-header">
      <div>
        <h1 class="page-title">{{ $t('accounting.balanceTitle') }}</h1>
        <p class="page-subtitle">{{ $t('accounting.balanceSubtitle') }}</p>
      </div>
    </div>

    <div class="filter-bar">
      <label class="filter-item">{{ $t('accounting.from') }}
        <input v-model="from" type="date" class="form-input" data-test="bal-from" @change="load" />
      </label>
      <label class="filter-item">{{ $t('accounting.to') }}
        <input v-model="to" type="date" class="form-input" data-test="bal-to" @change="load" />
      </label>
    </div>

    <div class="card table-scroll">
      <StateBlock v-if="loading" variant="loading" />
      <StateBlock v-else-if="rows.length === 0" variant="empty" :title="$t('accounting.noMovements')" />
      <table v-else class="data-table">
        <thead>
          <tr>
            <th>{{ $t('accounting.colNumber') }}</th>
            <th>{{ $t('accounting.colLabel') }}</th>
            <th class="num">{{ $t('accounting.opening') }}</th>
            <th class="num">{{ $t('accounting.debit') }}</th>
            <th class="num">{{ $t('accounting.credit') }}</th>
            <th class="num">{{ $t('accounting.balance') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="r in rows" :key="r.account_id" class="clickable" :data-test="`bal-row-${r.code}`" @click="openLedger(r)">
            <td><code class="acc-code">{{ r.code }}</code></td>
            <td>{{ r.name }}</td>
            <td class="num muted">{{ r.opening_minor ? fmt(r.opening_minor) : '—' }}</td>
            <td class="num">{{ r.debit_minor ? fmt(r.debit_minor) : '—' }}</td>
            <td class="num">{{ r.credit_minor ? fmt(r.credit_minor) : '—' }}</td>
            <td class="num" :class="r.closing_minor < 0 ? 'credit-bal' : 'debit-bal'">{{ fmtSigned(r.closing_minor) }}</td>
          </tr>
        </tbody>
        <tfoot>
          <tr class="totals-row">
            <td colspan="3">{{ $t('common.total') }}</td>
            <td class="num" data-test="bal-total-debit">{{ fmt(totals.debit_minor) }}</td>
            <td class="num" data-test="bal-total-credit">{{ fmt(totals.credit_minor) }}</td>
            <td class="num" :class="balanced ? 'debit-bal' : 'credit-bal'">{{ balanced ? '✓' : '⚠' }}</td>
          </tr>
        </tfoot>
      </table>
    </div>

    <!-- Grand livre d'un compte -->
    <BaseModal v-model="ledgerModal.open" :title="ledgerModal.title" size="lg">
      <StateBlock v-if="ledgerModal.loading" variant="loading" />
      <template v-else-if="ledgerModal.data">
        <p class="acc-hint">
          {{ $t('accounting.opening') }} : <strong>{{ fmtSigned(ledgerModal.data.opening_minor) }}</strong> ·
          {{ $t('accounting.balance') }} : <strong>{{ fmtSigned(ledgerModal.data.closing_minor) }}</strong>
        </p>
        <div class="table-scroll">
          <table class="data-table">
            <thead>
              <tr>
                <th>{{ $t('accounting.colDate') }}</th>
                <th>{{ $t('accounting.colJournal') }}</th>
                <th>{{ $t('accounting.colNumber') }}</th>
                <th>{{ $t('accounting.colLabel') }}</th>
                <th class="num">{{ $t('accounting.debit') }}</th>
                <th class="num">{{ $t('accounting.credit') }}</th>
                <th class="num">{{ $t('accounting.running') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(l, i) in ledgerModal.data.lines" :key="i" data-test="ledger-line">
                <td>{{ fmtDate(l.date) }}</td>
                <td><code class="acc-code">{{ l.journal }}</code></td>
                <td>{{ l.number ?? '—' }}</td>
                <td>{{ l.label ?? '—' }}</td>
                <td class="num">{{ l.debit_minor ? fmt(l.debit_minor) : '—' }}</td>
                <td class="num">{{ l.credit_minor ? fmt(l.credit_minor) : '—' }}</td>
                <td class="num" :class="l.running_minor < 0 ? 'credit-bal' : 'debit-bal'">{{ fmtSigned(l.running_minor) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
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
import type { TrialBalanceRow, GeneralLedger } from '../types'

const auth = useAuthStore()
const currency = computed(() => (auth.user as any)?.tenant?.settings?.currency ?? 'XOF')
const fmt = (cents: number) => formatMoney(cents ?? 0, currency.value)
const fmtSigned = (cents: number) => (cents < 0 ? '-' : '') + formatMoney(Math.abs(cents ?? 0), currency.value)
const fmtDate = formatDate

const rows    = ref<TrialBalanceRow[]>([])
const totals  = ref({ debit_minor: 0, credit_minor: 0, closing_debit_minor: 0, closing_credit_minor: 0 })
const loading = ref(false)
const from    = ref('')
const to      = ref('')

const balanced = computed(() => totals.value.debit_minor === totals.value.credit_minor)

async function load() {
  loading.value = true
  try {
    const tb = await accountingService.trialBalance({ from: from.value || undefined, to: to.value || undefined })
    rows.value = tb.rows
    totals.value = tb.totals
  } finally {
    loading.value = false
  }
}

const ledgerModal = reactive<{ open: boolean; loading: boolean; title: string; data: GeneralLedger | null }>({
  open: false, loading: false, title: '', data: null,
})

async function openLedger(r: TrialBalanceRow) {
  ledgerModal.open = true
  ledgerModal.loading = true
  ledgerModal.title = `${r.code} — ${r.name}`
  ledgerModal.data = null
  try {
    ledgerModal.data = await accountingService.generalLedger(r.account_id, { from: from.value || undefined, to: to.value || undefined })
  } finally {
    ledgerModal.loading = false
  }
}

onMounted(load)
</script>

<style scoped>
.page-container { padding: 24px; max-width: 1100px; margin: 0 auto; }
.page-header { margin-bottom: 20px; }
.page-title { font-size: 1.5rem; font-weight: 700; margin: 0; }
.page-subtitle { color: var(--gray-500); margin: 4px 0 0; font-size: 0.875rem; }
.filter-bar { display: flex; gap: 16px; margin-bottom: 16px; }
.filter-item { display: flex; flex-direction: column; gap: 4px; font-size: .8rem; color: var(--gray-600); }
.filter-item .form-input { max-width: 180px; }
.num { text-align: right; }
.muted { color: var(--gray-400); }
.acc-code { background: var(--gray-100); padding: 2px 6px; border-radius: 4px; font-size: .8rem; }
.acc-hint { font-size: .85rem; color: var(--gray-600); margin-bottom: 8px; }
.clickable { cursor: pointer; }
.clickable:hover { background: var(--gray-50); }
.debit-bal { color: var(--gray-800); }
.credit-bal { color: #b45309; }
.totals-row { font-weight: 700; border-top: 2px solid var(--gray-300); }
.table-scroll { overflow-x: auto; }
</style>
