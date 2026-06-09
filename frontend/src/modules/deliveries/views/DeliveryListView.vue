<template>
  <div>
    <SalesTabNav />
    <div class="page-header">
      <div>
        <h2>{{ $t('deliveries.title') }}</h2>
        <p class="page-subtitle">{{ meta.total ?? '—' }} {{ (meta.total ?? 0) !== 1 ? $t('deliveries.itemPlural') : $t('deliveries.itemSingular') }}</p>
      </div>
      <button class="btn btn-primary" @click="openCreate">
        <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
          <path d="M8 3v10M3 8h10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
        </svg>
        {{ $t('deliveries.new') }}
      </button>
    </div>

    <!-- Filters -->
    <div class="filter-bar">
      <select v-model="filters.status" class="form-input filter-select" style="max-width: 200px;" @change="load">
        <option value="">{{ $t('common.allStatuses') }}</option>
        <option value="pending">{{ $t('deliveries.status.pending') }}</option>
        <option value="dispatched">{{ $t('deliveries.status.dispatched') }}</option>
        <option value="in_transit">{{ $t('deliveries.status.in_transit') }}</option>
        <option value="delivered">{{ $t('deliveries.status.delivered') }}</option>
        <option value="failed">{{ $t('deliveries.status.failed') }}</option>
      </select>
    </div>

    <!-- Loading -->
    <StateBlock v-if="loading" variant="loading" />

    <!-- Empty -->
    <StateBlock
      v-else-if="deliveries.length === 0"
      variant="empty"
      :title="$t('deliveries.empty')"
      :message="filters.status ? $t('deliveries.emptyFiltered') : $t('deliveries.emptyDefault')"
    />

    <!-- Table -->
    <div v-else class="card table-scroll" style="padding: 0;">
      <table class="data-table">
        <thead>
          <tr>
            <th>{{ $t('common.status') }}</th>
            <th class="hide-mobile">{{ $t('deliveries.colOrder') }}</th>
            <th class="hide-mobile">{{ $t('deliveries.colCarrier') }}</th>
            <th class="hide-mobile">{{ $t('deliveries.colTracking') }}</th>
            <th>{{ $t('deliveries.colDates') }}</th>
            <th style="text-align: right;">{{ $t('common.actions') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="d in deliveries" :key="d.id">
            <td>
              <span :class="`badge ${statusBadge(d.status)}`">{{ statusLabel(d.status) }}</span>
            </td>
            <td class="hide-mobile">
              <RouterLink
                v-if="d.order_id"
                :to="`/orders/${d.order_id}`"
                style="font-family: monospace; font-size: 0.82rem; color: var(--brand-secondary);"
              >
                {{ d.order_number ?? d.order_id.slice(0, 8) + '…' }}
              </RouterLink>
              <span v-else class="text-muted">—</span>
            </td>
            <td class="hide-mobile">{{ d.carrier ?? '—' }}</td>
            <td class="hide-mobile">
              <span v-if="d.tracking_number" style="font-family: monospace; font-size: 0.82rem;">
                {{ d.tracking_number }}
              </span>
              <span v-else class="text-muted">—</span>
            </td>
            <td style="font-size: 0.8rem; color: var(--gray-600);">
              <div v-if="d.dispatched_at">📤 {{ fmtDate(d.dispatched_at) }}</div>
              <div v-if="d.delivered_at">✅ {{ fmtDate(d.delivered_at) }}</div>
              <div v-if="d.failed_at">❌ {{ fmtDate(d.failed_at) }}</div>
              <div v-if="!d.dispatched_at && !d.delivered_at && !d.failed_at" class="text-muted">
                {{ $t('deliveries.created', { date: fmtDate(d.created_at) }) }}
              </div>
            </td>
            <td>
              <div class="row-actions" style="justify-content: flex-end;">
                <!-- Dispatch -->
                <button
                  v-if="d.status === 'pending'"
                  class="btn btn-ghost btn-sm"
                  :disabled="actionId === d.id"
                  @click="doDispatch(d)"
                >
                  <span v-if="actionId === d.id" class="spinner-sm"></span>
                  {{ $t('deliveries.dispatch') }}
                </button>
                <!-- Deliver -->
                <button
                  v-if="d.status === 'dispatched' || d.status === 'in_transit'"
                  class="btn btn-ghost btn-sm"
                  style="color: #059669;"
                  :disabled="actionId === d.id"
                  @click="doDeliver(d)"
                >
                  <span v-if="actionId === d.id" class="spinner-sm"></span>
                  {{ $t('deliveries.markDelivered') }}
                </button>
                <!-- Fail -->
                <button
                  v-if="d.status !== 'delivered' && d.status !== 'failed'"
                  class="btn btn-ghost btn-sm"
                  style="color: #dc2626;"
                  :disabled="actionId === d.id"
                  @click="openFail(d)"
                >
                  {{ $t('deliveries.fail') }}
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div v-if="meta.last_page > 1" class="pagination">
      <button class="btn btn-ghost btn-sm" :disabled="meta.current_page <= 1" @click="goToPage(meta.current_page - 1)">← {{ $t('common.previous') }}</button>
      <span class="page-info">{{ $t('common.pageOf', { current: meta.current_page, total: meta.last_page }) }}</span>
      <button class="btn btn-ghost btn-sm" :disabled="meta.current_page >= meta.last_page" @click="goToPage(meta.current_page + 1)">{{ $t('common.next') }} →</button>
    </div>

    <!-- Create modal (shared BaseModal — UX-03) -->
    <BaseModal v-model="createModal.open" :title="$t('deliveries.new')">
      <div style="display: flex; flex-direction: column; gap: 14px;">
        <div class="form-group">
          <label class="form-label">{{ $t('deliveries.carrier') }}</label>
          <input v-model="createForm.carrier" type="text" class="form-input" placeholder="DHL, FedEx, Colissimo…" />
        </div>
        <div class="form-group">
          <label class="form-label">{{ $t('deliveries.tracking') }}</label>
          <input v-model="createForm.tracking_number" type="text" class="form-input" placeholder="ABC123456789" />
        </div>
        <div class="form-group">
          <label class="form-label">{{ $t('common.notes') }}</label>
          <textarea v-model="createForm.notes" class="form-input" rows="2" style="resize: vertical; min-height: 56px;"></textarea>
        </div>
        <p v-if="createModal.error" style="color: #dc2626; font-size: 0.875rem;">{{ createModal.error }}</p>
      </div>

      <template #footer>
        <button class="btn btn-ghost" @click="createModal.open = false">{{ $t('common.cancel') }}</button>
        <button class="btn btn-primary" :disabled="createModal.saving" @click="submitCreate">
          <span v-if="createModal.saving" class="spinner-sm"></span>
          {{ $t('common.create') }}
        </button>
      </template>
    </BaseModal>

    <!-- Fail reason modal (shared BaseModal — UX-03) -->
    <BaseModal v-model="failModal.open" size="sm" :title="$t('deliveries.failTitle')" :subtitle="failModal.orderRef">
      <div style="display: flex; flex-direction: column; gap: 12px;">
        <div class="form-group">
          <label class="form-label">{{ $t('deliveries.failReason') }} <span style="color:#dc2626;">*</span></label>
          <textarea v-model="failModal.reason" class="form-input" rows="3" style="resize: vertical;" :placeholder="$t('deliveries.failReasonPlaceholder')"></textarea>
        </div>
        <p v-if="failModal.error" style="color: #dc2626; font-size: 0.875rem;">{{ failModal.error }}</p>
      </div>

      <template #footer>
        <button class="btn btn-ghost" @click="failModal.open = false">{{ $t('common.cancel') }}</button>
        <button class="btn btn-danger" :disabled="failModal.saving || !failModal.reason.trim()" @click="submitFail">
          <span v-if="failModal.saving" class="spinner-sm"></span>
          {{ $t('deliveries.failConfirm') }}
        </button>
      </template>
    </BaseModal>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { formatDateShort } from '@/shared/utils/date'
import { RouterLink } from 'vue-router'
import SalesTabNav from '../../orders/components/SalesTabNav.vue'
import { deliveryService } from '../services/deliveryService'
import StateBlock from '@/shared/ui/StateBlock.vue'
import BaseModal from '@/shared/ui/BaseModal.vue'
import { t } from '@/i18n'
import type { Delivery, DeliveryStatus } from '../types'

const deliveries = ref<Delivery[]>([])
const loading    = ref(false)
const actionId   = ref<string | null>(null)
const meta       = reactive({ current_page: 1, last_page: 1, per_page: 20, total: 0 })
const filters    = reactive({ status: '', page: 1 })

// ── Create modal ──────────────────────────────────────────────────────────────
const createModal = reactive({ open: false, saving: false, error: '' })
const createForm  = reactive({ carrier: '', tracking_number: '', notes: '' })

// ── Fail modal ────────────────────────────────────────────────────────────────
const failModal = reactive({ open: false, saving: false, error: '', deliveryId: '', orderRef: '', reason: '' })

// ── Data ───────────────────────────────────────────────────────────────────────
async function load() {
  loading.value = true
  try {
    const params: Record<string, string | number> = { page: filters.page, per_page: meta.per_page }
    if (filters.status) params.status = filters.status
    const res = await deliveryService.list(params as any)
    deliveries.value = res.data
    Object.assign(meta, res.meta)
  } catch {
    deliveries.value = []
  } finally {
    loading.value = false
  }
}

function goToPage(page: number) { filters.page = page; load() }

// ── Actions ────────────────────────────────────────────────────────────────────
async function doDispatch(d: Delivery) {
  actionId.value = d.id
  try {
    await deliveryService.dispatch(d.id)
    load()
  } catch { /* ignore */ } finally { actionId.value = null }
}

async function doDeliver(d: Delivery) {
  actionId.value = d.id
  try {
    await deliveryService.deliver(d.id)
    load()
  } catch { /* ignore */ } finally { actionId.value = null }
}

function openFail(d: Delivery) {
  failModal.deliveryId = d.id
  failModal.orderRef   = d.order_number ?? d.order_id
  failModal.reason     = ''
  failModal.error      = ''
  failModal.saving     = false
  failModal.open       = true
}

async function submitFail() {
  if (!failModal.reason.trim()) return
  failModal.saving = true
  failModal.error  = ''
  try {
    await deliveryService.fail(failModal.deliveryId, failModal.reason)
    failModal.open = false
    load()
  } catch (e: any) {
    failModal.error = e?.response?.data?.message ?? t('common.genericError')
  } finally {
    failModal.saving = false
  }
}

// ── Create ─────────────────────────────────────────────────────────────────────
function openCreate() {
  createForm.carrier          = ''
  createForm.tracking_number  = ''
  createForm.notes            = ''
  createModal.error           = ''
  createModal.saving          = false
  createModal.open            = true
}

async function submitCreate() {
  createModal.saving = true
  createModal.error  = ''
  try {
    await deliveryService.create({
      carrier:         createForm.carrier || undefined,
      tracking_number: createForm.tracking_number || undefined,
      notes:           createForm.notes || undefined,
    })
    createModal.open = false
    load()
  } catch (e: any) {
    createModal.error = e?.response?.data?.message ?? t('common.genericError')
  } finally {
    createModal.saving = false
  }
}

// ── Formatters ────────────────────────────────────────────────────────────────
function statusLabel(s: DeliveryStatus): string {
  return t(`deliveries.status.${s}`)
}

function statusBadge(s: DeliveryStatus): string {
  return ({
    pending:    'badge-gray',
    dispatched: 'badge-blue',
    in_transit: 'badge-blue',
    delivered:  'badge-success',
    failed:     'badge-error',
  } as Record<DeliveryStatus, string>)[s] ?? 'badge-gray'
}

const fmtDate = formatDateShort

onMounted(load)
</script>
