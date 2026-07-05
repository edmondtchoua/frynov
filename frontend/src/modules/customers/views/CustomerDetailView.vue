<template>
  <div>
    <div class="page-header">
      <RouterLink to="/customers" class="btn btn-ghost">← {{ $t('customers.title') }}</RouterLink>
      <div v-if="customer && !editing" class="header-actions">
        <button class="btn btn-ghost" @click="editing = true">{{ $t('common.edit') }}</button>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="loading-center" style="min-height: 300px;">
      <span class="spinner-sm" style="width: 28px; height: 28px; border-width: 3px;"></span>
    </div>

    <!-- Error -->
    <div v-else-if="notFound" class="empty-state">
      <svg width="40" height="40" viewBox="0 0 40 40" fill="none">
        <rect x="4" y="4" width="32" height="32" rx="8" fill="#fff0f0"/>
        <path d="M20 12v12M20 28v2" stroke="var(--color-error)" stroke-width="2" stroke-linecap="round"/>
      </svg>
      <h3>{{ $t('customers.notFoundTitle') }}</h3>
      <p>{{ $t('customers.notFoundMsg') }}</p>
      <RouterLink to="/customers" class="btn btn-primary">{{ $t('customers.backToList') }}</RouterLink>
    </div>

    <template v-else-if="customer">

      <div class="detail-layout">

        <!-- Left: Customer info -->
        <div class="detail-main">

          <!-- View mode -->
          <div v-if="!editing" class="card">
            <div class="customer-header">
              <div class="customer-avatar-lg">{{ initials(customer.name) }}</div>
              <div>
                <h2 class="customer-full-name">{{ customer.name }}</h2>
                <div class="customer-since">{{ $t('customers.since', { date: formatDate(customer.created_at) }) }}</div>
              </div>
            </div>

            <div class="info-grid">
              <div class="info-item">
                <span class="info-label">{{ $t('common.email') }}</span>
                <span v-if="customer.email" class="info-value">
                  <a :href="`mailto:${customer.email}`" class="info-link">{{ customer.email }}</a>
                </span>
                <span v-else class="info-empty">{{ $t('customers.notProvided') }}</span>
              </div>
              <div class="info-item">
                <span class="info-label">{{ $t('common.phone') }}</span>
                <span v-if="customer.phone" class="info-value">
                  <a :href="`tel:${customer.phone}`" class="info-link">{{ customer.phone }}</a>
                </span>
                <span v-else class="info-empty">{{ $t('customers.notProvided') }}</span>
              </div>
              <div v-if="customer.address" class="info-item info-item--full">
                <span class="info-label">{{ $t('customers.colAddress') }}</span>
                <span class="info-value">{{ formatCustomerAddress(customer.address) }}</span>
              </div>
              <div v-if="customer.notes" class="info-item info-item--full">
                <span class="info-label">{{ $t('common.notes') }}</span>
                <span class="info-value notes-value">{{ customer.notes }}</span>
              </div>
            </div>
          </div>

          <!-- Edit mode -->
          <div v-else class="card">
            <h3 class="card-section-title">{{ $t('customers.editTitle') }}</h3>
            <div class="form-group">
              <label class="form-label">{{ $t('customers.fullName') }} *</label>
              <input v-model="form.name" type="text" class="form-input" :class="{ error: formErrors.name }"/>
              <span v-if="formErrors.name" class="form-error">{{ formErrors.name }}</span>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label class="form-label">{{ $t('common.email') }}</label>
                <input v-model="form.email" type="email" class="form-input" :placeholder="$t('customers.emailPlaceholder')"/>
              </div>
              <div class="form-group">
                <label class="form-label">{{ $t('common.phone') }}</label>
                <input v-model="form.phone" type="tel" class="form-input" :placeholder="$t('customers.phonePlaceholder')"/>
              </div>
            </div>

            <h4 class="subsection-title">{{ $t('customers.colAddress') }}</h4>
            <div class="form-row">
              <div class="form-group">
                <label class="form-label">{{ $t('customers.street') }}</label>
                <input v-model="form.address.street" data-testid="customer-address-street" type="text" class="form-input"/>
              </div>
              <div class="form-group">
                <label class="form-label">{{ $t('customers.city') }}</label>
                <input v-model="form.address.city" type="text" class="form-input"/>
              </div>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label class="form-label">{{ $t('customers.zip') }}</label>
                <input v-model="form.address.zip" type="text" class="form-input"/>
              </div>
              <div class="form-group">
                <label class="form-label">{{ $t('customers.country') }}</label>
                <input v-model="form.address.country" type="text" class="form-input"/>
              </div>
            </div>

            <div class="form-group" style="margin-bottom: 0;">
              <label class="form-label">{{ $t('common.notes') }}</label>
              <textarea v-model="form.notes" class="form-input" rows="3"></textarea>
            </div>

            <div v-if="saveError" class="alert alert-error" style="margin-top: 1rem;">{{ saveError }}</div>

            <div class="edit-actions">
              <button class="btn btn-ghost" @click="cancelEdit">{{ $t('common.cancel') }}</button>
              <button class="btn btn-primary" :disabled="saving" @click="saveCustomer">
                <span v-if="saving" class="spinner-sm spinner-white"></span>
                {{ saving ? $t('common.saving') : $t('common.update') }}
              </button>
            </div>
          </div>

          <!-- Orders -->
          <div class="card">
            <div class="card-section-header">
              <h3 class="card-section-title" style="margin-bottom: 0; border: none; padding: 0;">
                {{ $t('customers.colOrders') }} ({{ customer.orders_count ?? orders.length }})
              </h3>
              <RouterLink to="/orders/new" class="btn btn-ghost btn-sm">{{ $t('customers.newOrder') }}</RouterLink>
            </div>

            <div v-if="ordersLoading" class="loading-center" style="min-height: 100px; padding: 1rem 0;">
              <span class="spinner-sm"></span>
            </div>
            <div v-else-if="orders.length === 0" class="orders-empty">
              <p>{{ $t('customers.noOrders') }}</p>
            </div>
            <table v-else class="data-table" style="margin-top: 1rem;">
              <thead>
                <tr>
                  <th>{{ $t('customers.colRef') }}</th>
                  <th>{{ $t('common.status') }}</th>
                  <th>{{ $t('common.amount') }}</th>
                  <th>{{ $t('common.date') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="order in orders" :key="order.id">
                  <td>
                    <RouterLink :to="`/orders/${order.id}`" class="order-link">{{ order.number }}</RouterLink>
                  </td>
                  <td><span :class="orderStatusBadge(order.status)">{{ orderStatusLabel(order.status) }}</span></td>
                  <td>{{ formatAmount(order.total_amount, order.currency) }}</td>
                  <td class="text-muted">{{ formatDate(order.created_at) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Right: Stats sidebar -->
        <div class="detail-side">
          <div class="card stat-card">
            <div class="stat-item">
              <span class="stat-label">{{ $t('customers.colOrders') }}</span>
              <span class="stat-value">{{ customer.orders_count ?? '—' }}</span>
            </div>
            <div class="stat-divider"></div>
            <div class="stat-item">
              <span class="stat-label">{{ $t('customers.sinceLabel') }}</span>
              <span class="stat-value-sm">{{ formatDate(customer.created_at) }}</span>
            </div>
          </div>

          <div class="card">
            <h3 class="card-section-title">{{ $t('common.actions') }}</h3>
            <div class="action-list">
              <RouterLink to="/orders/new" class="action-btn">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                  <path d="M8 3v10M3 8h10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                </svg>
                {{ $t('customers.createOrder') }}
              </RouterLink>
              <button class="action-btn action-btn--danger" @click="deleteCustomer">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                  <path d="M3 4h10M6 4V3h4v1M5 4v8a1 1 0 001 1h4a1 1 0 001-1V4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
                </svg>
                {{ $t('customers.deleteCustomer') }}
              </button>
            </div>
          </div>
        </div>

      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { formatDate } from '@/shared/utils/date'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import { formatMoney } from '@/shared/utils/money'
import { customerService } from '../services/customerService'
import { formatCustomerAddress, normalizeCustomerAddress } from '../address'
import { t } from '@/i18n'
import type { Customer } from '../types'

const route    = useRoute()
const router   = useRouter()
const id       = route.params.id as string

const customer     = ref<Customer | null>(null)
const orders       = ref<any[]>([])
const loading      = ref(false)
const ordersLoading = ref(false)
const notFound     = ref(false)
const editing      = ref(false)
const saving       = ref(false)
const saveError    = ref('')

const form = reactive({
  name: '', email: '', phone: '', notes: '',
  address: { street: '', city: '', zip: '', country: '' },
})
const formErrors = reactive<Record<string, string>>({})

function initials(name: string): string {
  return name.split(' ').slice(0, 2).map(n => n[0]?.toUpperCase() ?? '').join('')
}


const formatAmount = (cents: number, currency: string) => formatMoney(cents, currency)

function orderStatusBadge(status: string): string {
  return {
    draft: 'badge badge-gray',
    confirmed: 'badge badge-blue',
    fulfilled: 'badge badge-success',
    cancelled: 'badge badge-warning',
  }[status] ?? 'badge badge-gray'
}

function orderStatusLabel(status: string): string {
  return ({
    draft:     t('orders.status.draft'),
    confirmed: t('orders.status.confirmed'),
    fulfilled: t('orders.status.fulfilled'),
    cancelled: t('orders.status.cancelled'),
  } as Record<string, string>)[status] ?? status
}

function populateForm(c: Customer) {
  form.name    = c.name
  form.email   = c.email ?? ''
  form.phone   = c.phone ?? ''
  form.notes   = c.notes ?? ''
  Object.assign(form.address, normalizeCustomerAddress(c.address))
}

function cancelEdit() {
  if (customer.value) populateForm(customer.value)
  editing.value = false
  saveError.value = ''
}

async function saveCustomer() {
  if (!form.name.trim()) { formErrors.name = t('customers.nameRequired'); return }
  saving.value    = true
  saveError.value = ''
  try {
    const address = Object.values(form.address).some(Boolean)
      ? { street: form.address.street || undefined, city: form.address.city || undefined, zip: form.address.zip || undefined, country: form.address.country || undefined }
      : undefined
    customer.value = await customerService.update(id, {
      name:    form.name,
      email:   form.email   || undefined,
      phone:   form.phone   || undefined,
      notes:   form.notes   || undefined,
      address,
    })
    editing.value = false
  } catch {
    saveError.value = t('customers.saveError')
  } finally {
    saving.value = false
  }
}

async function deleteCustomer() {
  if (!confirm(t('customers.deleteConfirm', { name: customer.value?.name ?? '' }))) return
  try {
    await customerService.delete(id)
    router.push('/customers')
  } catch { /* ignore */ }
}

onMounted(async () => {
  loading.value = true
  try {
    customer.value = await customerService.get(id)
    populateForm(customer.value)
  } catch {
    notFound.value = true
  } finally {
    loading.value = false
  }

  ordersLoading.value = true
  orders.value = await customerService.orders(id).catch(() => [])
  ordersLoading.value = false
})
</script>

<style scoped>
.header-actions { display: flex; gap: 0.75rem; }

.detail-layout {
  display: grid;
  grid-template-columns: 1fr 280px;
  gap: 1.5rem;
  align-items: flex-start;
}
@media (max-width: 900px) { .detail-layout { grid-template-columns: 1fr; } }

.card-section-title {
  font-size: var(--text-base); font-weight: 600; color: var(--gray-900);
  margin: 0 0 1.25rem; padding-bottom: 0.75rem; border-bottom: 1px solid var(--gray-100);
}
.card-section-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 0; }
.subsection-title { font-size: var(--text-sm); font-weight: 600; color: var(--gray-700); margin: 1.25rem 0 0.75rem; }

.customer-header { display: flex; align-items: center; gap: 1rem; margin-bottom: 1.5rem; }
.customer-avatar-lg {
  width: 56px; height: 56px; border-radius: 50%;
  background: var(--brand-secondary-light); color: var(--brand-secondary-dark);
  display: flex; align-items: center; justify-content: center;
  font-weight: 700; font-size: var(--text-xl); flex-shrink: 0;
}
.customer-full-name { font-size: var(--text-xl); font-weight: 700; color: var(--gray-900); margin: 0 0 0.2rem; }
.customer-since { font-size: var(--text-sm); color: var(--gray-400); }

.info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
.info-item { display: flex; flex-direction: column; gap: 0.25rem; }
.info-item--full { grid-column: 1 / -1; }
.info-label { font-size: var(--text-xs); font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: var(--gray-400); }
.info-value { font-size: var(--text-sm); color: var(--gray-900); }
.info-link  { color: var(--brand-secondary); text-decoration: none; }
.info-link:hover { text-decoration: underline; }
.info-empty { font-size: var(--text-sm); color: var(--gray-300); font-style: italic; }
.notes-value { white-space: pre-wrap; line-height: 1.6; }

.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
@media (max-width: 600px) { .form-row { grid-template-columns: 1fr; } }
.edit-actions { display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--gray-100); }

.orders-empty { padding: 1.5rem 0; text-align: center; }
.orders-empty p { font-size: var(--text-sm); color: var(--gray-400); margin: 0; }
.order-link { color: var(--brand-secondary); text-decoration: none; font-weight: 500; font-size: var(--text-sm); }
.order-link:hover { text-decoration: underline; }
.text-muted { color: var(--gray-400); font-size: var(--text-sm); }

.stat-card .stat-item { display: flex; align-items: center; justify-content: space-between; }
.stat-label { font-size: var(--text-sm); color: var(--gray-500); }
.stat-value { font-size: var(--text-2xl); font-weight: 700; color: var(--gray-900); }
.stat-value-sm { font-size: var(--text-sm); font-weight: 600; color: var(--gray-700); }
.stat-divider { height: 1px; background: var(--gray-100); margin: 1rem 0; }

.action-list { display: flex; flex-direction: column; gap: 0.375rem; }
.action-btn {
  display: flex; align-items: center; gap: 0.6rem;
  padding: 0.6rem 0.75rem; border-radius: var(--radius-md);
  font-size: var(--text-sm); font-weight: 500; cursor: pointer;
  background: var(--gray-50); border: 1px solid var(--gray-200);
  color: var(--gray-700); text-decoration: none;
  transition: background 0.12s, border-color 0.12s;
}
.action-btn:hover { background: var(--gray-100); border-color: var(--gray-300); }
.action-btn--danger { color: var(--color-error); background: #fff5f5; border-color: #fecaca; }
.action-btn--danger:hover { background: #fee2e2; border-color: var(--color-error); }
</style>
