<template>
  <div class="page-container">
    <AccountingTabNav />

    <div class="page-header">
      <div>
        <h1 class="page-title">{{ $t('accounting.statementsTitle') }}</h1>
        <p class="page-subtitle">{{ $t('accounting.statementsSubtitle') }}</p>
      </div>
    </div>

    <div class="filter-bar">
      <div class="seg">
        <button :class="['seg-btn', { active: mode === 'balance' }]" data-test="tab-bilan" @click="switchMode('balance')">{{ $t('accounting.balanceSheet') }}</button>
        <button :class="['seg-btn', { active: mode === 'income' }]" data-test="tab-resultat" @click="switchMode('income')">{{ $t('accounting.incomeStatement') }}</button>
      </div>
      <label class="filter-item">{{ $t('accounting.from') }}
        <input v-model="from" type="date" class="form-input" data-test="st-from" @change="load" />
      </label>
      <label class="filter-item">{{ $t('accounting.to') }}
        <input v-model="to" type="date" class="form-input" data-test="st-to" @change="load" />
      </label>
    </div>

    <StateBlock v-if="loading" variant="loading" />

    <!-- Bilan -->
    <div v-else-if="mode === 'balance' && bs" class="statement-grid">
      <div class="card statement-col">
        <h3 class="statement-head">{{ $t('accounting.actif') }}</h3>
        <table class="data-table">
          <tbody>
            <tr v-for="r in bs.actif" :key="r.code"><td><code class="acc-code">{{ r.code }}</code> {{ r.name }}</td><td class="num">{{ fmt(r.amount_minor) }}</td></tr>
          </tbody>
          <tfoot><tr class="totals-row"><td>{{ $t('accounting.totalActif') }}</td><td class="num" data-test="bs-total-actif">{{ fmt(bs.total_actif_minor) }}</td></tr></tfoot>
        </table>
      </div>
      <div class="card statement-col">
        <h3 class="statement-head">{{ $t('accounting.passif') }}</h3>
        <table class="data-table">
          <tbody>
            <tr v-for="r in bs.passif" :key="r.code" :class="{ 'result-row': r.code === '13' }">
              <td><code class="acc-code">{{ r.code }}</code> {{ r.name }}</td><td class="num">{{ fmt(r.amount_minor) }}</td>
            </tr>
          </tbody>
          <tfoot><tr class="totals-row"><td>{{ $t('accounting.totalPassif') }}</td><td class="num" data-test="bs-total-passif">{{ fmt(bs.total_passif_minor) }}</td></tr></tfoot>
        </table>
      </div>
      <p class="balance-check" :class="bs.balanced ? 'ok' : 'ko'" data-test="bs-balanced">
        {{ bs.balanced ? $t('accounting.balancedOk') : $t('accounting.balancedKo') }}
      </p>
    </div>

    <!-- Compte de résultat -->
    <div v-else-if="mode === 'income' && is" class="statement-grid">
      <div class="card statement-col">
        <h3 class="statement-head">{{ $t('accounting.charges') }}</h3>
        <table class="data-table">
          <tbody>
            <tr v-for="r in is.charges" :key="r.code"><td><code class="acc-code">{{ r.code }}</code> {{ r.name }}</td><td class="num">{{ fmt(r.amount_minor) }}</td></tr>
          </tbody>
          <tfoot><tr class="totals-row"><td>{{ $t('accounting.totalCharges') }}</td><td class="num">{{ fmt(is.total_charges_minor) }}</td></tr></tfoot>
        </table>
      </div>
      <div class="card statement-col">
        <h3 class="statement-head">{{ $t('accounting.produits') }}</h3>
        <table class="data-table">
          <tbody>
            <tr v-for="r in is.produits" :key="r.code"><td><code class="acc-code">{{ r.code }}</code> {{ r.name }}</td><td class="num">{{ fmt(r.amount_minor) }}</td></tr>
          </tbody>
          <tfoot><tr class="totals-row"><td>{{ $t('accounting.totalProduits') }}</td><td class="num" data-test="is-total-produits">{{ fmt(is.total_produits_minor) }}</td></tr></tfoot>
        </table>
      </div>
      <p class="result-banner" :class="is.result_minor >= 0 ? 'profit' : 'loss'" data-test="is-result">
        {{ is.result_minor >= 0 ? $t('accounting.profitLabel') : $t('accounting.lossLabel') }} : <strong>{{ fmt(Math.abs(is.result_minor)) }}</strong>
      </p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import AccountingTabNav from '../components/AccountingTabNav.vue'
import StateBlock from '@/shared/ui/StateBlock.vue'
import { formatMoney } from '@/shared/utils/money'
import { useAuthStore } from '@/stores/auth'
import { accountingService } from '../services/accountingService'
import type { BalanceSheet, IncomeStatement } from '../types'

const auth = useAuthStore()
const currency = computed(() => (auth.user as any)?.tenant?.settings?.currency ?? 'XOF')
const fmt = (c: number) => formatMoney(c ?? 0, currency.value)

const mode    = ref<'balance' | 'income'>('balance')
const from    = ref('')
const to      = ref('')
const loading = ref(false)
const bs      = ref<BalanceSheet | null>(null)
const is      = ref<IncomeStatement | null>(null)

function switchMode(m: 'balance' | 'income') {
  mode.value = m
  load()
}

async function load() {
  loading.value = true
  const params = { from: from.value || undefined, to: to.value || undefined }
  try {
    if (mode.value === 'balance') bs.value = await accountingService.balanceSheet(params)
    else is.value = await accountingService.incomeStatement(params)
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<style scoped>
.page-container { padding: 24px; max-width: 1100px; margin: 0 auto; }
.page-header { margin-bottom: 20px; }
.page-title { font-size: 1.5rem; font-weight: 700; margin: 0; }
.page-subtitle { color: var(--gray-500); margin: 4px 0 0; font-size: 0.875rem; }
.filter-bar { display: flex; gap: 20px; align-items: flex-end; margin-bottom: 16px; flex-wrap: wrap; }
.seg { display: inline-flex; border: 1px solid var(--gray-300); border-radius: 8px; overflow: hidden; }
.seg-btn { padding: 8px 16px; font-size: .85rem; background: #fff; border: none; cursor: pointer; color: var(--gray-600); }
.seg-btn.active { background: var(--brand-primary); color: #fff; font-weight: 600; }
.filter-item { display: flex; flex-direction: column; gap: 4px; font-size: .8rem; color: var(--gray-600); }
.filter-item .form-input { max-width: 170px; }
.statement-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.statement-col { padding: 8px 12px; }
.statement-head { font-size: 1rem; margin: 4px 0 8px; }
.num { text-align: right; }
.acc-code { background: var(--gray-100); padding: 1px 5px; border-radius: 4px; font-size: .78rem; }
.totals-row { font-weight: 700; border-top: 2px solid var(--gray-300); }
.result-row { color: var(--brand-primary); font-weight: 600; }
.balance-check { grid-column: 1 / -1; text-align: center; font-size: .9rem; margin: 4px 0 0; }
.balance-check.ok { color: #15803d; }
.balance-check.ko { color: #b91c1c; }
.result-banner { grid-column: 1 / -1; text-align: center; font-size: 1rem; padding: 10px; border-radius: 8px; margin: 4px 0 0; }
.result-banner.profit { background: #dcfce7; color: #166534; }
.result-banner.loss { background: #fee2e2; color: #991b1b; }
@media (max-width: 720px) { .statement-grid { grid-template-columns: 1fr; } }
</style>
