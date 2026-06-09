<template>
  <div>
    <div class="page-header">
      <div>
        <h2>{{ $t('customers.title') }}</h2>
        <p class="page-subtitle">{{ $t('customers.count', { count: meta.total ?? '—' }) }}</p>
      </div>
      <button class="btn btn-primary" @click="openCreate">
        <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
          <path d="M8 3v10M3 8h10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
        </svg>
        {{ $t('customers.new') }}
      </button>
    </div>

    <!-- Search -->
    <div class="filter-bar">
      <div class="search-wrap">
        <svg class="search-icon" width="16" height="16" viewBox="0 0 16 16" fill="none">
          <circle cx="7" cy="7" r="4.5" stroke="var(--gray-400)" stroke-width="1.4"/>
          <path d="M10.5 10.5l2.5 2.5" stroke="var(--gray-400)" stroke-width="1.4" stroke-linecap="round"/>
        </svg>
        <input
          v-model="search"
          type="text"
          class="form-input search-input"
          :placeholder="$t('customers.searchPlaceholder')"
          @input="debouncedLoad"
        />
      </div>
    </div>

    <!-- Loading -->
    <StateBlock v-if="loading" variant="loading" />

    <!-- Empty -->
    <StateBlock
      v-else-if="customers.length === 0"
      variant="empty"
      :title="$t('customers.emptyTitle')"
      :message="search ? $t('customers.emptySearch') : $t('customers.emptyFirst')"
    >
      <template v-if="!search" #action>
        <button class="btn btn-primary" @click="openCreate">{{ $t('customers.add') }}</button>
      </template>
    </StateBlock>

    <!-- Table -->
    <div v-else class="card table-scroll" style="padding: 0;">
      <table class="data-table data-table--cards">
        <thead>
          <tr>
            <th>{{ $t('customers.colClient') }}</th>
            <th class="hide-mobile">{{ $t('common.phone') }}</th>
            <th class="hide-mobile">{{ $t('customers.colAddress') }}</th>
            <th class="hide-mobile">{{ $t('common.notes') }}</th>
            <th class="hide-mobile">{{ $t('customers.colOrders') }}</th>
            <th style="text-align: right;">{{ $t('common.actions') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="customer in customers" :key="customer.id">
            <td class="cell-primary">
              <div class="customer-cell">
                <div class="customer-avatar">{{ initials(customer.name) }}</div>
                <div>
                  <div class="customer-name">{{ customer.name }}</div>
                  <div v-if="customer.email" class="customer-email">{{ customer.email }}</div>
                </div>
              </div>
            </td>
            <td class="hide-mobile" :data-label="$t('common.phone')">
              <span v-if="customer.phone" class="customer-phone">{{ customer.phone }}</span>
              <span v-else class="text-muted">—</span>
            </td>
            <td class="hide-mobile" :data-label="$t('customers.colAddress')">
              <span v-if="formatCustomerAddress(customer.address)" class="customer-address">{{ formatCustomerAddress(customer.address) }}</span>
              <span v-else class="text-muted">—</span>
            </td>
            <td class="hide-mobile" :data-label="$t('common.notes')">
              <span v-if="customer.notes" class="customer-notes">{{ customer.notes }}</span>
              <span v-else class="text-muted">—</span>
            </td>
            <td class="hide-mobile" :data-label="$t('customers.colOrders')">
              <span class="badge badge-gray">{{ customer.orders_count ?? 0 }}</span>
            </td>
            <td class="cell-actions" style="text-align: right;">
              <div class="row-actions">
                <RouterLink :to="`/customers/${customer.id}`" class="btn btn-ghost btn-sm">
                  {{ $t('customers.view') }}
                </RouterLink>
                <button class="btn btn-ghost btn-sm" @click="openEdit(customer)">{{ $t('common.edit') }}</button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div v-if="meta.last_page > 1" class="pagination">
      <button class="btn btn-ghost btn-sm" :disabled="meta.current_page <= 1" @click="goToPage(meta.current_page - 1)">
        ← {{ $t('common.previous') }}
      </button>
      <span class="page-info">{{ $t('common.pageOf', { current: meta.current_page, total: meta.last_page }) }}</span>
      <button class="btn btn-ghost btn-sm" :disabled="meta.current_page >= meta.last_page" @click="goToPage(meta.current_page + 1)">
        {{ $t('common.next') }} →
      </button>
    </div>

    <!-- Create / Edit modal (shared BaseModal — UX-03) -->
    <BaseModal
      :model-value="showModal"
      :title="editingCustomer ? $t('customers.editTitle') : $t('customers.new')"
      @update:model-value="(v: boolean) => { if (!v) closeModal() }"
    >
      <div class="form-group">
        <label class="form-label">{{ $t('customers.fullName') }} <span style="color: var(--color-error);">*</span></label>
        <input v-model="form.name" type="text" class="form-input" :class="{ error: formErrors.name }" :placeholder="$t('customers.namePlaceholder')" @input="delete formErrors.name"/>
        <span v-if="formErrors.name" class="form-error">{{ formErrors.name }}</span>
      </div>
      <div class="form-row">
        <div class="form-group" style="margin-bottom: 0;">
          <label class="form-label">{{ $t('common.email') }}</label>
          <input v-model="form.email" type="email" class="form-input" :placeholder="$t('customers.emailPlaceholder')"/>
        </div>
        <div class="form-group" style="margin-bottom: 0;">
          <label class="form-label">{{ $t('common.phone') }}</label>
          <input v-model="form.phone" type="tel" class="form-input" :placeholder="$t('customers.phonePlaceholder')"/>
        </div>
      </div>
      <div class="form-group" style="margin-bottom: 0;">
        <label class="form-label">{{ $t('common.notes') }}</label>
        <textarea v-model="form.notes" class="form-input" rows="2" :placeholder="$t('customers.notesPlaceholder')"></textarea>
      </div>

      <template #footer>
        <button class="btn btn-ghost" @click="closeModal">{{ $t('common.cancel') }}</button>
        <button class="btn btn-primary" :disabled="saving" @click="saveCustomer">
          <span v-if="saving" class="spinner-sm spinner-white"></span>
          {{ saving ? $t('common.saving') : (editingCustomer ? $t('common.update') : $t('common.create')) }}
        </button>
      </template>
    </BaseModal>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { customerService } from '../services/customerService'
import { formatCustomerAddress } from '../address'
import { t } from '@/i18n'
import StateBlock from '@/shared/ui/StateBlock.vue'
import BaseModal from '@/shared/ui/BaseModal.vue'
import type { Customer, CustomerAddress } from '../types'

const customers = ref<Customer[]>([])
const loading   = ref(false)
const saving    = ref(false)
const search    = ref('')
const page      = ref(1)
const meta      = reactive({ current_page: 1, last_page: 1, per_page: 20, total: 0 })

const showModal      = ref(false)
const editingCustomer = ref<Customer | null>(null)
const form       = reactive({ name: '', email: '', phone: '', notes: '' })
const formErrors = reactive<Record<string, string>>({})

let debounceTimer: ReturnType<typeof setTimeout>
function debouncedLoad() {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => { page.value = 1; load() }, 300)
}

async function load() {
  loading.value = true
  try {
    const res = await customerService.list({ search: search.value || undefined, page: page.value })
    customers.value = res.data
    Object.assign(meta, res.meta)
  } catch {
    customers.value = []
  } finally {
    loading.value = false
  }
}

function goToPage(p: number) { page.value = p; load() }

function initials(name: string): string {
  return name.split(' ').slice(0, 2).map(n => n[0]?.toUpperCase() ?? '').join('')
}

function formatAddress(addr: CustomerAddress | null | undefined): string {
  if (!addr) return ''
  if (typeof addr === 'string') return addr
  return [addr.street, addr.zip, addr.city, addr.country].filter(Boolean).join(', ')
}

function openCreate() {
  editingCustomer.value = null
  form.name  = ''; form.email = ''; form.phone = ''; form.notes = ''
  Object.keys(formErrors).forEach(k => delete formErrors[k])
  showModal.value = true
}

function openEdit(c: Customer) {
  editingCustomer.value = c
  form.name  = c.name
  form.email = c.email ?? ''
  form.phone = c.phone ?? ''
  form.notes = c.notes ?? ''
  showModal.value = true
}

function closeModal() { showModal.value = false; editingCustomer.value = null }

async function saveCustomer() {
  if (!form.name.trim()) { formErrors.name = t('customers.nameRequired'); return }
  saving.value = true
  try {
    const data = {
      name:  form.name,
      email: form.email || undefined,
      phone: form.phone || undefined,
      notes: form.notes || undefined,
    }
    if (editingCustomer.value) {
      await customerService.update(editingCustomer.value.id, data)
    } else {
      await customerService.create(data)
    }
    closeModal()
    load()
  } catch { /* ignore */ } finally {
    saving.value = false
  }
}

onMounted(load)
</script>

<style scoped>
.page-subtitle { color: var(--gray-500); font-size: var(--text-sm); margin-top: 0.2rem; }
.filter-bar { display: flex; gap: 0.75rem; margin-bottom: 1rem; }
.search-wrap { position: relative; flex: 1; max-width: 400px; }
.search-icon { position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%); pointer-events: none; }
.search-input { padding-left: 2.25rem !important; }
.text-muted { color: var(--gray-400); font-size: var(--text-sm); }
.row-actions { display: flex; gap: 0.5rem; justify-content: flex-end; }

.customer-cell { display: flex; align-items: center; gap: 0.75rem; }
.customer-avatar {
  width: 36px; height: 36px; border-radius: 50%;
  background: var(--brand-secondary-light);
  color: var(--brand-secondary-dark);
  display: flex; align-items: center; justify-content: center;
  font-weight: 700; font-size: var(--text-sm); flex-shrink: 0;
}
.customer-name  { font-weight: 500; color: var(--gray-900); font-size: var(--text-sm); }
.customer-email { font-size: var(--text-xs); color: var(--gray-400); margin-top: 1px; }
.customer-phone, .customer-address, .customer-notes { font-size: var(--text-sm); color: var(--gray-600); }
.customer-address, .customer-notes { display: inline-block; max-width: 220px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; vertical-align: middle; }

.pagination { display: flex; align-items: center; justify-content: center; gap: 1rem; margin-top: 1.5rem; }
.page-info  { font-size: var(--text-sm); color: var(--gray-500); }

.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
@media (max-width: 500px) { .form-row { grid-template-columns: 1fr; } }
/* Modal chrome now provided by the shared <BaseModal> (UX-03). */
</style>
