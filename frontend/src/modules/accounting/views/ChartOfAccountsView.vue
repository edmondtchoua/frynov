<template>
  <div class="page-container">
    <AccountingTabNav />

    <div class="page-header">
      <div>
        <h1 class="page-title">{{ $t('accounting.chartTitle') }}</h1>
        <p class="page-subtitle">{{ $t('accounting.chartSubtitle') }}</p>
      </div>
      <button v-if="provisioned" class="btn btn-primary" data-test="add-account" @click="openCreate">
        {{ $t('accounting.addAccount') }}
      </button>
    </div>

    <!-- Référentiel non initialisé → assistant de provisionnement -->
    <div v-if="loaded && !provisioned" class="card acc-provision" data-test="provision-box">
      <h3>{{ $t('accounting.provisionTitle') }}</h3>
      <p>{{ $t('accounting.provisionDesc') }}</p>
      <p v-if="provisionError" class="form-error" data-test="provision-error">{{ provisionError }}</p>
      <button class="btn btn-primary" :disabled="provisioning" data-test="provision" @click="provision">
        {{ provisioning ? $t('accounting.provisioning') : $t('accounting.provisionButton') }}
      </button>
    </div>

    <template v-else-if="loaded">
      <div class="filter-bar">
        <select v-model="classFilter" class="form-input filter-select" data-test="class-filter" @change="page = 1; load()">
          <option value="">{{ $t('accounting.allClasses') }}</option>
          <option v-for="c in classes" :key="c.code" :value="String(c.code)">{{ c.code }} — {{ c.name }}</option>
        </select>
        <input
          v-model="search" class="form-input" style="max-width: 260px"
          :placeholder="$t('accounting.searchAccount')" data-test="account-search" @input="debouncedLoad"
        />
      </div>

      <div class="card table-scroll">
        <StateBlock v-if="loading" variant="loading" />
        <StateBlock v-else-if="accounts.length === 0" variant="empty" :title="$t('accounting.noAccounts')" />
        <table v-else class="data-table">
          <thead>
            <tr>
              <th>{{ $t('accounting.colCode') }}</th>
              <th>{{ $t('accounting.colName') }}</th>
              <th>{{ $t('accounting.colClass') }}</th>
              <th>{{ $t('accounting.colKind') }}</th>
              <th>{{ $t('common.status') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="a in accounts" :key="a.id">
              <td><code class="acc-code">{{ a.code }}</code></td>
              <td>
                {{ a.name }}
                <span v-if="a.is_system" class="badge badge-gray acc-sys">{{ $t('accounting.system') }}</span>
              </td>
              <td>{{ a.class_code }}</td>
              <td>{{ $t('accounting.kind.' + a.kind) }}</td>
              <td>
                <span :class="a.is_active ? 'badge badge-success' : 'badge badge-gray'">
                  {{ a.is_active ? $t('accounting.active') : $t('accounting.inactive') }}
                </span>
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
    </template>

    <!-- Création de compte -->
    <BaseModal v-model="createModal.open" :title="$t('accounting.addAccount')">
      <div class="form-group">
        <label class="form-label">{{ $t('accounting.colCode') }} *</label>
        <input v-model="createModal.code" class="form-input" placeholder="7011" data-test="new-code" />
        <p class="acc-hint">{{ $t('accounting.codeHint') }}</p>
      </div>
      <div class="form-group">
        <label class="form-label">{{ $t('accounting.colName') }} *</label>
        <input v-model="createModal.name" class="form-input" data-test="new-name" />
      </div>
      <div class="form-group">
        <label class="form-label">{{ $t('accounting.colKind') }} *</label>
        <select v-model="createModal.kind" class="form-input" data-test="new-kind">
          <option value="asset">{{ $t('accounting.kind.asset') }}</option>
          <option value="liability">{{ $t('accounting.kind.liability') }}</option>
          <option value="equity">{{ $t('accounting.kind.equity') }}</option>
          <option value="revenue">{{ $t('accounting.kind.revenue') }}</option>
          <option value="expense">{{ $t('accounting.kind.expense') }}</option>
        </select>
      </div>
      <p v-if="createModal.error" class="form-error" data-test="create-error">{{ createModal.error }}</p>
      <template #footer>
        <button class="btn btn-ghost" @click="createModal.open = false">{{ $t('common.cancel') }}</button>
        <button class="btn btn-primary" :disabled="createModal.saving || !createModal.code || !createModal.name" data-test="create-submit" @click="submitCreate">
          {{ $t('common.save') }}
        </button>
      </template>
    </BaseModal>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import AccountingTabNav from '../components/AccountingTabNav.vue'
import StateBlock from '@/shared/ui/StateBlock.vue'
import BaseModal from '@/shared/ui/BaseModal.vue'
import { accountingService } from '../services/accountingService'
import { t } from '@/i18n'
import type { Account, AccountClass } from '../types'

const loaded       = ref(false)
const provisioned  = ref(false)
const provisioning = ref(false)
const provisionError = ref('')
const classes      = ref<AccountClass[]>([])

const accounts    = ref<Account[]>([])
const loading     = ref(false)
const classFilter = ref('')
const search      = ref('')
const page        = ref(1)
const meta        = ref({ current_page: 1, last_page: 1, total: 0 })

async function boot() {
  try {
    const o = await accountingService.overview()
    provisioned.value = o.provisioned
    classes.value = o.classes
    if (o.provisioned) await load()
  } finally {
    loaded.value = true
  }
}

async function load() {
  loading.value = true
  try {
    const res = await accountingService.accounts({
      class: classFilter.value ? Number(classFilter.value) : undefined,
      search: search.value || undefined,
      page: page.value,
      per_page: 50,
    })
    accounts.value = res.data
    meta.value = res.meta
  } finally {
    loading.value = false
  }
}

let timer: ReturnType<typeof setTimeout> | null = null
function debouncedLoad() {
  if (timer) clearTimeout(timer)
  timer = setTimeout(() => { page.value = 1; load() }, 300)
}

async function provision() {
  provisioning.value = true
  provisionError.value = ''
  try {
    await accountingService.provision()
    provisioned.value = true
    await load()
  } catch (e: any) {
    provisionError.value = e?.response?.data?.message ?? t('accounting.provisionError')
  } finally {
    provisioning.value = false
  }
}

const createModal = reactive({ open: false, saving: false, error: '', code: '', name: '', kind: 'revenue' })

function openCreate() {
  Object.assign(createModal, { open: true, saving: false, error: '', code: '', name: '', kind: 'revenue' })
}

async function submitCreate() {
  createModal.saving = true
  createModal.error = ''
  try {
    await accountingService.createAccount({ code: createModal.code.trim(), name: createModal.name.trim(), kind: createModal.kind })
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

onMounted(boot)
</script>

<style scoped>
.page-container { padding: 24px; max-width: 1100px; margin: 0 auto; }
.page-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; }
.page-title { font-size: 1.5rem; font-weight: 700; margin: 0; }
.page-subtitle { color: var(--gray-500); margin: 4px 0 0; font-size: 0.875rem; }
.filter-bar { display: flex; gap: 12px; margin-bottom: 16px; }
.filter-select { max-width: 320px; }
.acc-provision { padding: 2rem; text-align: center; }
.acc-provision h3 { margin: 0 0 .5rem; }
.acc-provision p { color: var(--gray-500); margin: 0 0 1.25rem; }
.acc-code { background: var(--gray-100); padding: 2px 6px; border-radius: 4px; font-size: .82rem; }
.acc-sys { font-size: .68rem; margin-left: 6px; }
.acc-hint { font-size: .78rem; color: var(--gray-500); margin: 4px 0 0; }
.form-error { color: #dc2626; font-size: .85rem; }
.pagination { display: flex; align-items: center; justify-content: center; gap: 1rem; margin-top: 1.5rem; font-size: .875rem; color: #6b7280; }
.table-scroll { overflow-x: auto; }
</style>
