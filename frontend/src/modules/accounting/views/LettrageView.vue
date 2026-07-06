<template>
  <div class="page-container">
    <AccountingTabNav />

    <div class="page-header">
      <div>
        <h1 class="page-title">{{ $t('accounting.lettrageTitle') }}</h1>
        <p class="page-subtitle">{{ $t('accounting.lettrageSubtitle') }}</p>
      </div>
    </div>

    <div class="filter-bar">
      <label class="filter-item">{{ $t('accounting.account') }}
        <select v-model="accountId" class="form-input" data-test="let-account" @change="load">
          <option value="">{{ $t('accounting.pickAccount') }}</option>
          <option v-for="a in accounts" :key="a.id" :value="a.id">{{ a.code }} — {{ a.name }}</option>
        </select>
      </label>
      <label class="filter-item checkbox-item">
        <input v-model="onlyOpen" type="checkbox" data-test="let-only-open" @change="load" /> {{ $t('accounting.onlyOpen') }}
      </label>
    </div>

    <div v-if="actionError" class="action-error" role="alert" data-test="let-error">
      {{ actionError }}<button class="action-error-close" @click="actionError = ''">×</button>
    </div>

    <template v-if="data">
      <div class="summary-bar">
        <span>{{ $t('accounting.openBalance') }} : <strong data-test="let-open-balance">{{ fmtSigned(data.summary.open_balance_minor) }}</strong></span>
        <span class="muted">{{ $t('accounting.lettered') }} : {{ fmt(data.summary.lettered_debit_minor) }}</span>
        <span v-if="selectedIds.size" class="sel-info" :class="{ balanced: selBalanced }">
          {{ $t('accounting.selection') }} : {{ fmt(selDebit) }} / {{ fmt(selCredit) }}
          <button class="btn btn-sm btn-primary" :disabled="!selBalanced || saving" data-test="let-submit" @click="letter">{{ $t('accounting.letterAction') }}</button>
        </span>
      </div>

      <div class="card table-scroll">
        <StateBlock v-if="data.lines.length === 0" variant="empty" :title="$t('accounting.noMovements')" />
        <table v-else class="data-table">
          <thead>
            <tr>
              <th></th>
              <th>{{ $t('accounting.colDate') }}</th>
              <th>{{ $t('accounting.colJournal') }}</th>
              <th>{{ $t('accounting.colNumber') }}</th>
              <th>{{ $t('accounting.colLabel') }}</th>
              <th class="num">{{ $t('accounting.debit') }}</th>
              <th class="num">{{ $t('accounting.credit') }}</th>
              <th>{{ $t('accounting.lettrageCode') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="l in data.lines" :key="l.id" :data-test="`let-line-${l.id}`" :class="{ lettered: l.lettrage_code }">
              <td>
                <input v-if="!l.lettrage_code" type="checkbox" :checked="selectedIds.has(l.id)" :data-test="`let-check-${l.id}`" @change="toggle(l.id)" />
              </td>
              <td>{{ fmtDate(l.date) }}</td>
              <td><code class="acc-code">{{ l.journal }}</code></td>
              <td>{{ l.number ?? '—' }}</td>
              <td>{{ l.label ?? '—' }}</td>
              <td class="num">{{ l.debit_minor ? fmt(l.debit_minor) : '—' }}</td>
              <td class="num">{{ l.credit_minor ? fmt(l.credit_minor) : '—' }}</td>
              <td>
                <button v-if="l.lettrage_code" class="code-badge" :data-test="`unletter-${l.lettrage_code}`" :title="$t('accounting.unletter')" @click="unletter(l.lettrage_code)">{{ l.lettrage_code }} ×</button>
                <span v-else class="muted">—</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>
    <StateBlock v-else-if="loading" variant="loading" />
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import AccountingTabNav from '../components/AccountingTabNav.vue'
import StateBlock from '@/shared/ui/StateBlock.vue'
import { formatMoney } from '@/shared/utils/money'
import { formatDate } from '@/shared/utils/date'
import { useAuthStore } from '@/stores/auth'
import { accountingService } from '../services/accountingService'
import { t } from '@/i18n'
import type { Account, LettrageData } from '../types'

const auth = useAuthStore()
const currency = computed(() => (auth.user as any)?.tenant?.settings?.currency ?? 'XOF')
const fmt = (c: number) => formatMoney(c ?? 0, currency.value)
const fmtSigned = (c: number) => (c < 0 ? '-' : '') + formatMoney(Math.abs(c ?? 0), currency.value)
const fmtDate = formatDate

const accounts    = ref<Account[]>([])
const accountId   = ref('')
const onlyOpen    = ref(false)
const data        = ref<LettrageData | null>(null)
const loading     = ref(false)
const saving      = ref(false)
const actionError = ref('')
const selectedIds = reactive(new Set<string>())

const selDebit  = computed(() => (data.value?.lines ?? []).filter(l => selectedIds.has(l.id)).reduce((s, l) => s + l.debit_minor, 0))
const selCredit = computed(() => (data.value?.lines ?? []).filter(l => selectedIds.has(l.id)).reduce((s, l) => s + l.credit_minor, 0))
const selBalanced = computed(() => selectedIds.size >= 2 && selDebit.value === selCredit.value && selDebit.value > 0)

function toggle(id: string) {
  selectedIds.has(id) ? selectedIds.delete(id) : selectedIds.add(id)
}

async function load() {
  selectedIds.clear()
  data.value = null
  if (!accountId.value) return
  loading.value = true
  try {
    data.value = await accountingService.lettrage(accountId.value, onlyOpen.value)
  } finally {
    loading.value = false
  }
}

async function letter() {
  saving.value = true
  actionError.value = ''
  try {
    const res = await accountingService.letterLines(accountId.value, [...selectedIds])
    selectedIds.clear()
    data.value = res.account
  } catch (e: any) {
    actionError.value = e?.response?.data?.message
      ?? (Object.values(e?.response?.data?.errors ?? {}).flat()[0] as string)
      ?? t('accounting.letterFailed')
  } finally {
    saving.value = false
  }
}

async function unletter(code: string) {
  actionError.value = ''
  try {
    const res = await accountingService.unletterCode(accountId.value, code)
    data.value = res.account
  } catch (e: any) {
    actionError.value = e?.response?.data?.message ?? t('accounting.unletterFailed')
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
.filter-bar { display: flex; gap: 20px; align-items: flex-end; margin-bottom: 16px; }
.filter-item { display: flex; flex-direction: column; gap: 4px; font-size: .8rem; color: var(--gray-600); }
.filter-item .form-input { min-width: 320px; }
.checkbox-item { flex-direction: row; align-items: center; gap: 6px; }
.summary-bar { display: flex; gap: 20px; align-items: center; margin-bottom: 12px; font-size: .9rem; flex-wrap: wrap; }
.sel-info { display: flex; align-items: center; gap: 10px; color: #b45309; }
.sel-info.balanced { color: #15803d; }
.num { text-align: right; }
.muted { color: var(--gray-400); }
.acc-code { background: var(--gray-100); padding: 2px 6px; border-radius: 4px; font-size: .8rem; }
tr.lettered { background: var(--gray-50); color: var(--gray-500); }
.code-badge { border: 1px solid var(--brand-primary); color: var(--brand-primary); background: transparent; border-radius: 4px; padding: 1px 6px; font-size: .78rem; font-weight: 600; cursor: pointer; }
.code-badge:hover { background: #fee2e2; border-color: #dc2626; color: #dc2626; }
.action-error { display: flex; justify-content: space-between; align-items: center; gap: 12px; background: #fee2e2; color: #991b1b; border-radius: 8px; padding: 10px 14px; margin-bottom: 16px; font-size: 0.875rem; }
.action-error-close { border: none; background: transparent; color: inherit; font-size: 1.1rem; cursor: pointer; }
.table-scroll { overflow-x: auto; }
</style>
