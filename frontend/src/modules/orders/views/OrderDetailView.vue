<template>
  <div style="max-width: 820px;">
    <!-- Header -->
    <div class="page-header">
      <div>
        <RouterLink to="/orders" class="back-link">← {{ $t('orders.title') }}</RouterLink>
        <h2>{{ order?.number ?? '…' }}</h2>
      </div>
      <span v-if="order" class="badge" :class="statusBadge(order.status)">
        {{ statusLabel(order.status) }}
      </span>
    </div>

    <div v-if="loading" class="loading-center" style="min-height: 300px;">
      <span class="spinner-sm" style="width: 24px; height: 24px; border-width: 3px;"></span>
    </div>

    <div v-else-if="error" class="empty-state">
      <h3>{{ $t('orders.detail.errorTitle') }}</h3>
      <p>{{ error }}</p>
    </div>

    <template v-else-if="order">
      <!-- ── Order lines ──────────────────────────────────────────────────── -->
      <div class="card" style="padding: 0; overflow: hidden; margin-bottom: 16px;">
        <table class="data-table">
          <thead>
            <tr>
              <th>{{ $t('orders.detail.sku') }}</th>
              <th>{{ $t('common.product') }}</th>
              <th style="text-align:right;">{{ $t('common.quantity') }}</th>
              <th style="text-align:right;" class="hide-mobile">{{ $t('orders.detail.unitPrice') }}</th>
              <th style="text-align:right;">{{ $t('orders.detail.subtotal') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="line in order.lines" :key="line.id">
              <td><code style="font-size: 0.8rem;">{{ line.sku }}</code></td>
              <td>{{ line.name }}</td>
              <td style="text-align:right;">{{ line.quantity }}</td>
              <td style="text-align:right;" class="hide-mobile">{{ fmt(line.unit_price_cents) }}</td>
              <td style="text-align:right; font-weight: 600;">{{ fmt(line.quantity * line.unit_price_cents) }}</td>
            </tr>
          </tbody>
          <tfoot>
            <tr>
              <td colspan="3" class="hide-mobile" style="text-align: right; font-weight: 700; padding: 10px 16px;">{{ $t('common.total') }}</td>
              <td class="show-mobile-only" style="text-align: right; font-weight: 700; padding: 10px 16px;">{{ $t('common.total') }}</td>
              <td style="text-align: right; font-weight: 700; padding: 10px 16px; font-size: 1.05rem;">
                {{ fmt(order.total_amount) }}
              </td>
            </tr>
          </tfoot>
        </table>
      </div>

      <!-- ── Two-column layout (meta + payments | actions) ──────────────── -->
      <div class="detail-grid">

        <!-- Left column -->
        <div style="display: flex; flex-direction: column; gap: 16px;">

          <!-- Meta -->
          <div class="card" style="font-size: 0.875rem; color: var(--gray-600);">
            <div v-if="order.note" style="margin-bottom: 8px;"><strong>{{ $t('common.note') }} :</strong> {{ order.note }}</div>
            <div><strong>{{ $t('orders.detail.createdLabel') }}</strong> {{ fmtDate(order.created_at) }}</div>
            <div v-if="order.fulfilled_at" style="margin-top: 4px;"><strong>{{ $t('orders.detail.fulfilledLabel') }}</strong> {{ fmtDate(order.fulfilled_at) }}</div>
            <div v-if="order.cancelled_at" style="margin-top: 4px;"><strong>{{ $t('orders.detail.cancelledLabel') }}</strong> {{ fmtDate(order.cancelled_at) }}</div>
          </div>

          <!-- ── Payments panel ────────────────────────────────────────────── -->
          <div class="card">
            <div class="panel-header">
              <h4 class="panel-title">{{ $t('payments.title') }}</h4>
              <button class="btn btn-ghost btn-sm" @click="openPaymentModal">
                <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                  <path d="M7 2v10M2 7h10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                </svg>
                {{ $t('common.save') }}
              </button>
            </div>

            <!-- Balance summary -->
            <div class="balance-row">
              <div class="balance-info">
                <span class="balance-label">{{ $t('orders.detail.collected') }}</span>
                <span class="balance-value" :style="payBalance >= order.total_amount ? 'color:#059669;' : ''">
                  {{ fmt(payBalance) }}
                </span>
              </div>
              <div class="balance-info">
                <span class="balance-label">{{ $t('common.total') }}</span>
                <span class="balance-value">{{ fmt(order.total_amount) }}</span>
              </div>
              <div class="balance-info">
                <span class="balance-label">{{ $t('orders.detail.remaining') }}</span>
                <span class="balance-value" :style="payBalance < order.total_amount ? 'color:#b45309;' : 'color:#059669;'">
                  {{ fmt(Math.max(0, order.total_amount - payBalance)) }}
                </span>
              </div>
              <span v-if="payIsFullyPaid" class="badge badge-success" style="align-self: center;">{{ $t('orders.detail.settled') }} ✓</span>
              <span v-else class="badge badge-warning" style="align-self: center;">{{ $t('orders.detail.partial') }}</span>
            </div>

            <!-- Payment list -->
            <div v-if="payLoading" class="loading-center" style="min-height: 60px; padding: 12px;">
              <span class="spinner-sm"></span>
            </div>
            <div v-else-if="payments.length === 0" style="font-size: 0.85rem; color: var(--gray-400); padding: 8px 0;">
              {{ $t('orders.detail.noPayments') }}
            </div>
            <div v-else class="pay-list">
              <div v-for="p in payments" :key="p.id" class="pay-item">
                <span :class="`badge ${methodBadge(p.method)}`" style="font-size: 0.72rem;">{{ methodLabel(p.method) }}</span>
                <span style="font-size: 0.85rem; color: var(--gray-600);">{{ fmtDateShort(p.paid_at) }}</span>
                <span v-if="p.reference" style="font-family: monospace; font-size: 0.78rem; color: var(--gray-500);">{{ p.reference }}</span>
                <span class="pay-amount">{{ fmt(p.amount_cents) }}</span>
                <button class="btn-icon-sm" :title="$t('common.cancel')" @click="voidPayment(p.id)">
                  <svg width="12" height="12" viewBox="0 0 12 12" fill="none">
                    <path d="M3 3l6 6M9 3l-6 6" stroke="var(--gray-400)" stroke-width="1.3" stroke-linecap="round"/>
                  </svg>
                </button>
              </div>
            </div>

            <!-- Surfaced void/record errors (was silently swallowed before) -->
            <p v-if="payActionError" class="form-error" style="margin-top:8px;font-size:0.8rem;">{{ payActionError }}</p>
          </div>

          <!-- ── Delivery panel ─────────────────────────────────────────────── -->
          <div class="card">
            <div class="panel-header">
              <h4 class="panel-title">{{ $t('orders.detail.delivery') }}</h4>
              <RouterLink to="/deliveries" class="btn btn-ghost btn-sm">{{ $t('orders.detail.manage') }} →</RouterLink>
            </div>

            <div v-if="deliveryLoading" class="loading-center" style="min-height: 60px; padding: 12px;">
              <span class="spinner-sm"></span>
            </div>
            <div v-else-if="deliveries.length === 0" style="font-size: 0.85rem; color: var(--gray-400); padding: 8px 0;">
              {{ $t('orders.detail.noDelivery') }}
            </div>
            <div v-else class="delivery-list">
              <div v-for="d in deliveries" :key="d.id" class="delivery-item">
                <span :class="`badge ${deliveryStatusBadge(d.status)}`" style="font-size: 0.72rem;">
                  {{ deliveryStatusLabel(d.status) }}
                </span>
                <span v-if="d.carrier" style="font-size: 0.85rem; color: var(--gray-600);">{{ d.carrier }}</span>
                <span v-if="d.tracking_number" style="font-family: monospace; font-size: 0.78rem; color: var(--gray-500);">
                  {{ d.tracking_number }}
                </span>
                <span style="font-size: 0.78rem; color: var(--gray-400);">
                  {{ fmtDateShort(d.delivered_at ?? d.dispatched_at ?? d.created_at) }}
                </span>
              </div>
            </div>
          </div>

          <!-- ── Serialized units panel (RC-5C) — only for serialized products ─── -->
          <div v-if="units.length > 0" class="card">
            <div class="panel-header">
              <h4 class="panel-title">{{ $t('orders.detail.unitsTitle') }}</h4>
              <span class="badge badge-gray" style="font-size: 0.72rem;">{{ units.length }}</span>
            </div>
            <div class="unit-list">
              <div v-for="u in units" :key="u.id" class="unit-item">
                <span class="unit-type">{{ u.serial_type.toUpperCase() }}</span>
                <span class="unit-serial">{{ u.serial_value }}</span>
                <span :class="`badge ${unitStatusBadge(u.status)}`" style="font-size: 0.72rem; margin-left: auto;">
                  {{ unitStatusLabel(u.status) }}
                </span>
              </div>
            </div>
          </div>

          <!-- ── Warranty contracts panel (RC-5D) — only when contracts exist ──── -->
          <div v-if="warranties.length > 0" class="card">
            <div class="panel-header">
              <h4 class="panel-title">{{ $t('orders.detail.warrantiesTitle') }}</h4>
              <span class="badge badge-gray" style="font-size: 0.72rem;">{{ warranties.length }}</span>
            </div>
            <div class="unit-list">
              <div v-for="w in warranties" :key="w.id" class="unit-item">
                <span class="unit-serial">{{ w.policy_name || w.product_name }}</span>
                <span v-if="w.serial_value" class="unit-type">{{ w.serial_value }}</span>
                <span class="warranty-until">{{ $t('orders.detail.warrantyUntil', { date: fmtDateShort(w.ends_at) }) }}</span>
                <span :class="`badge ${warrantyStatusBadge(w.status)}`" style="font-size: 0.72rem; margin-left: auto;">
                  {{ warrantyStatusLabel(w.status) }}
                </span>
              </div>
            </div>
          </div>

          <!-- ── Digital access panel (RC-5E) — only for digital products ──────── -->
          <div v-if="entitlements.length > 0" class="card">
            <div class="panel-header">
              <h4 class="panel-title">{{ $t('orders.detail.entitlementsTitle') }}</h4>
              <span class="badge badge-gray" style="font-size: 0.72rem;">{{ entitlements.length }}</span>
            </div>
            <div class="unit-list">
              <div v-for="e in entitlements" :key="e.id" class="unit-item">
                <span class="unit-serial">{{ e.product_name }}</span>
                <span class="unit-type">{{ $t('orders.detail.entitlementType.' + e.fulfillment_type) }}</span>
                <span :class="`badge ${entitlementStatusBadge(e.status)}`" style="font-size: 0.72rem; margin-left: auto;">
                  {{ entitlementStatusLabel(e.status) }}
                </span>
              </div>
            </div>
          </div>
        </div>

        <!-- Right column — actions -->
        <div style="display: flex; flex-direction: column; gap: 12px;">
          <div class="card">
            <h4 class="panel-title" style="margin-bottom: 12px;">{{ $t('common.actions') }}</h4>
            <div style="display: flex; flex-direction: column; gap: 8px;">
              <button
                v-if="order.status === 'draft'"
                class="btn btn-primary"
                :disabled="actionLoading !== false"
                @click="act('confirm')"
              >
                <span v-if="actionLoading === 'confirm'" class="spinner-sm"></span>
                {{ $t('orders.detail.confirmOrder') }}
              </button>

              <button
                v-if="order.status === 'confirmed'"
                class="btn btn-primary"
                :disabled="actionLoading !== false"
                @click="act('fulfill')"
              >
                <span v-if="actionLoading === 'fulfill'" class="spinner-sm"></span>
                {{ $t('orders.detail.markFulfilled') }}
              </button>

              <button
                v-if="order.status === 'draft' || order.status === 'confirmed'"
                class="btn btn-danger"
                :disabled="actionLoading !== false"
                @click="act('cancel')"
              >
                <span v-if="actionLoading === 'cancel'" class="spinner-sm"></span>
                {{ $t('common.cancel') }}
              </button>

              <RouterLink :to="`/inventory/movements/${order.lines[0]?.product_id}`" class="btn btn-ghost btn-sm" v-if="order.lines.length > 0">
                {{ $t('orders.detail.viewStock') }} →
              </RouterLink>
            </div>

            <div v-if="actionError" class="form-error" style="margin-top: 12px;">{{ actionError }}</div>
          </div>
        </div>
      </div>
    </template>

    <!-- ── Record payment modal (shared BaseModal — UX-03) ───────────────────── -->
    <BaseModal
      v-model="payModal.open"
      :title="$t('payments.modalTitle')"
      :subtitle="order ? $t('orders.detail.paySubtitle', { number: order.number, remaining: fmt(Math.max(0, (order.total_amount ?? 0) - payBalance)) }) : ''"
    >
      <div style="display: flex; flex-direction: column; gap: 14px;">
        <div class="form-group">
          <label class="form-label">{{ $t('common.amount') }} <span style="color:#dc2626;">*</span></label>
          <!-- Devise verrouillée sur celle de la commande (le solde somme les centimes
               sans conversion) → affichée en suffixe du champ. -->
          <div class="input-affix">
            <input
              v-model.number="payForm.amount"
              type="number" min="0" step="0.01"
              class="form-input"
              placeholder="0"
            />
            <span class="input-affix__suffix">{{ payForm.currency }}</span>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">{{ $t('payments.colMethod') }} <span style="color:#dc2626;">*</span></label>
          <!-- P6 — moyens spécifiques au marché si disponibles, sinon catégories canoniques -->
          <select v-if="marketMethods.length" v-model="payForm.provider" class="form-input">
            <option v-for="pm in marketMethods" :key="pm.method" :value="pm.method">
              {{ $t('billing.payMethods.method.' + pm.method) }} · {{ $t('billing.payMethods.mode.' + pm.mode) }}
            </option>
          </select>
          <select v-else v-model="payForm.method" class="form-input">
            <option value="cash">{{ $t('payments.method.cash') }}</option>
            <option value="mobile_money">{{ $t('payments.method.mobile_money') }}</option>
            <option value="card">{{ $t('payments.method.card') }}</option>
            <option value="transfer">{{ $t('payments.method.transfer') }}</option>
            <option value="cheque">{{ $t('payments.method.cheque') }}</option>
          </select>
        </div>

        <div class="form-group">
          <label class="form-label">{{ $t('payments.colReference') }}</label>
          <input v-model="payForm.reference" type="text" class="form-input" :placeholder="$t('payments.referencePlaceholder')" />
        </div>

        <p v-if="payModal.error" style="color:#dc2626; font-size:0.875rem;">{{ payModal.error }}</p>
      </div>

      <template #footer>
        <button class="btn btn-ghost" @click="payModal.open = false">{{ $t('common.cancel') }}</button>
        <button
          class="btn btn-primary"
          :disabled="payModal.saving || !payForm.amount || payForm.amount <= 0"
          @click="submitPayment"
        >
          <span v-if="payModal.saving" class="spinner-sm"></span>
          {{ $t('common.save') }}
        </button>
      </template>
    </BaseModal>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { formatDateTime, formatDateShort } from '@/shared/utils/date'
import { useRoute, RouterLink } from 'vue-router'
import { formatMoney } from '@/shared/utils/money'
import { orderService } from '../services/orderService'
import { paymentService } from '@/modules/payments/services/paymentService'
import { deliveryService } from '@/modules/deliveries/services/deliveryService'
import { useGeoContent } from '@/composables/useGeoContent'
import { fetchPublicPaymentMethods, type PublicPaymentMethod } from '@/services/publicPricingService'
import BaseModal from '@/shared/ui/BaseModal.vue'
import { useConfirm } from '@/composables/useConfirm'
import { t } from '@/i18n'
import type { Order, OrderUnit, OrderWarranty, OrderEntitlement } from '../types'
import type { Payment, PaymentMethod } from '@/modules/payments/types'
import type { Delivery, DeliveryStatus } from '@/modules/deliveries/types'

const route = useRoute()
const id    = route.params.id as string

// ── Order ──────────────────────────────────────────────────────────────────────
const order         = ref<Order | null>(null)
const loading       = ref(false)
const error         = ref<string | null>(null)
const actionLoading = ref<string | false>(false)
const actionError   = ref<string | null>(null)

// ── Payments ───────────────────────────────────────────────────────────────────
const payments     = ref<Payment[]>([])
const payLoading   = ref(false)
const payBalance   = ref(0)
const payIsFullyPaid = ref(false)
const payActionError = ref<string | null>(null)   // surfaced void/record errors
const payModal     = reactive({ open: false, saving: false, error: '' })
const payForm      = reactive({ amount: undefined as number | undefined, currency: 'XOF', method: 'cash' as PaymentMethod, provider: '', reference: '' })

// ── P6 — moyens de paiement spécifiques au marché (sinon repli sur les catégories canoniques) ──
const { market } = useGeoContent()
const marketMethods = ref<PublicPaymentMethod[]>([])

async function loadMarketMethods() {
  const code = market.value.code === 'africa' ? 'waemu' : market.value.code
  try {
    marketMethods.value = (await fetchPublicPaymentMethods({ market: code })).data
  } catch {
    marketMethods.value = [] // repli : le <select> canonique reste affiché
  }
}

// ── Deliveries ─────────────────────────────────────────────────────────────────
const deliveries      = ref<Delivery[]>([])
const deliveryLoading = ref(false)

// ── Serialized units (RC-5C) — empty unless the order carries serialized products ─
const units = ref<OrderUnit[]>([])

// ── Warranty contracts (RC-5D) — empty unless a sold product had a warranty policy ─
const warranties = ref<OrderWarranty[]>([])

// ── Digital entitlements (RC-5E) — empty unless a digital product was sold ─────────
const entitlements = ref<OrderEntitlement[]>([])

// ── Load ───────────────────────────────────────────────────────────────────────
async function load() {
  loading.value = true
  error.value   = null
  try {
    order.value = await orderService.get(id)
  } catch {
    error.value = t('orders.detail.notFound')
  } finally {
    loading.value = false
  }
}

async function loadPayments() {
  payLoading.value = true
  try {
    const res  = await paymentService.forOrder(id)
    payments.value    = res.data
    payBalance.value  = res.balance
    payIsFullyPaid.value = res.is_fully_paid
  } catch {
    payments.value = []
  } finally {
    payLoading.value = false
  }
}

async function loadDeliveries() {
  deliveryLoading.value = true
  try {
    const res     = await deliveryService.forOrder(id)
    deliveries.value = res.data
  } catch {
    deliveries.value = []
  } finally {
    deliveryLoading.value = false
  }
}

// RC-5C — unités sérialisées rattachées (silencieux : vide pour les produits non sérialisés).
async function loadUnits() {
  try {
    units.value = (await orderService.units(id)).data
  } catch {
    units.value = []
  }
}

// RC-5D — contrats de garantie générés (silencieux : vide si aucun produit sous garantie vendu).
async function loadWarranties() {
  try {
    warranties.value = (await orderService.warranties(id)).data
  } catch {
    warranties.value = []
  }
}

// RC-5E — droits d'accès digitaux (silencieux : vide pour les produits non digitaux).
async function loadEntitlements() {
  try {
    entitlements.value = (await orderService.entitlements(id)).data
  } catch {
    entitlements.value = []
  }
}

// ── Order actions ──────────────────────────────────────────────────────────────
async function act(action: 'confirm' | 'fulfill' | 'cancel') {
  actionLoading.value = action
  actionError.value   = null
  try {
    order.value = await orderService[action](id)
    loadUnits()         // RC-5C — les statuts d'unités changent (réservé/vendu/dispo) selon l'action.
    loadWarranties()    // RC-5D — le fulfill génère les contrats de garantie.
    loadEntitlements()  // RC-5E — le fulfill génère les droits d'accès digitaux.
  } catch (e: any) {
    actionError.value = e?.response?.data?.message ?? t('orders.detail.actionError', { action })
  } finally {
    actionLoading.value = false
  }
}

// ── Payment modal ──────────────────────────────────────────────────────────────
function openPaymentModal() {
  const remaining = Math.max(0, (order.value?.total_amount ?? 0) - payBalance.value)
  payForm.amount    = remaining > 0 ? remaining / 100 : undefined
  payForm.currency  = order.value?.currency ?? 'XOF'
  payForm.method    = 'cash'
  payForm.provider  = marketMethods.value[0]?.method ?? ''
  payForm.reference = ''
  payModal.error    = ''
  payModal.saving   = false
  payModal.open     = true
}

async function submitPayment() {
  if (!payForm.amount || payForm.amount <= 0) return
  payModal.saving = true
  payModal.error  = ''
  try {
    // P6 — si un moyen spécifique du marché est sélectionné, on poste `provider`
    // (le backend en dérive la catégorie canonique `method`) ; sinon `method` direct.
    await paymentService.record({
      order_id:     id,
      amount_cents: Math.round(payForm.amount * 100),
      currency:     payForm.currency,
      ...(payForm.provider ? { provider: payForm.provider } : { method: payForm.method }),
      reference:    payForm.reference || undefined,
    })
    payModal.open = false
    loadPayments()
  } catch (e: any) {
    payModal.error = e?.response?.data?.message ?? t('common.genericError')
  } finally {
    payModal.saving = false
  }
}

const { confirm } = useConfirm()

async function voidPayment(paymentId: string) {
  if (!(await confirm({
    title: t('orders.detail.voidTitle'),
    message: t('orders.detail.voidConfirm'),
    danger: true,
  }))) return
  payActionError.value = null
  try {
    await paymentService.void(paymentId)
    loadPayments()
  } catch (e: any) {
    payActionError.value = e?.response?.data?.message ?? t('orders.detail.voidError')
  }
}

// ── Formatters ────────────────────────────────────────────────────────────────
function statusLabel(s: string) {
  return t(`orders.status.${s}`)
}
function statusBadge(s: string) {
  return { draft: 'badge-gray', confirmed: 'badge-blue', fulfilled: 'badge-success', cancelled: 'badge-error' }[s] ?? ''
}
function methodLabel(m: PaymentMethod): string {
  return t(`payments.method.${m}`)
}
function methodBadge(m: PaymentMethod): string {
  return ({ cash: 'badge-success', mobile_money: 'badge-blue', card: 'badge-blue', transfer: 'badge-gray', cheque: 'badge-gray' } as Record<PaymentMethod, string>)[m] ?? 'badge-gray'
}
function deliveryStatusLabel(s: DeliveryStatus): string {
  return t(`deliveries.status.${s}`)
}
function deliveryStatusBadge(s: DeliveryStatus): string {
  return ({ pending: 'badge-gray', dispatched: 'badge-blue', in_transit: 'badge-blue', delivered: 'badge-success', failed: 'badge-error' } as Record<DeliveryStatus, string>)[s] ?? 'badge-gray'
}
function unitStatusLabel(s: OrderUnit['status']): string {
  return t(`orders.detail.unitStatus.${s}`)
}
function unitStatusBadge(s: OrderUnit['status']): string {
  return ({ in_stock: 'badge-gray', reserved: 'badge-blue', sold: 'badge-success', returned: 'badge-warning', repair: 'badge-warning', quarantine: 'badge-gray', lost: 'badge-error', scrapped: 'badge-error' } as Record<OrderUnit['status'], string>)[s] ?? 'badge-gray'
}
function warrantyStatusLabel(s: OrderWarranty['status']): string {
  return t(`orders.detail.warrantyStatus.${s}`)
}
function warrantyStatusBadge(s: OrderWarranty['status']): string {
  return ({ active: 'badge-success', expired: 'badge-gray', void: 'badge-error' } as Record<OrderWarranty['status'], string>)[s] ?? 'badge-gray'
}
function entitlementStatusLabel(s: OrderEntitlement['status']): string {
  return t(`orders.detail.entitlementStatus.${s}`)
}
function entitlementStatusBadge(s: OrderEntitlement['status']): string {
  return ({ active: 'badge-success', revoked: 'badge-error', expired: 'badge-gray' } as Record<OrderEntitlement['status'], string>)[s] ?? 'badge-gray'
}
function fmt(cents: number) {
  return formatMoney(cents, order.value?.currency ?? 'XOF')
}
const fmtDate = formatDateTime
const fmtDateShort = formatDateShort

onMounted(() => { load(); loadPayments(); loadDeliveries(); loadUnits(); loadWarranties(); loadEntitlements(); loadMarketMethods() })
</script>

<style scoped>
.back-link { font-size: 0.8rem; color: var(--gray-500); text-decoration: none; display: inline-block; margin-bottom: 4px; }
.back-link:hover { color: var(--brand-primary); }

.detail-grid {
  display: grid;
  grid-template-columns: 1fr 280px;
  gap: 16px;
  align-items: start;
}
@media (max-width: 768px) {
  .detail-grid { grid-template-columns: 1fr; }
}

.panel-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; }
.panel-title  { font-size: 0.9rem; font-weight: 600; color: var(--gray-800); margin: 0; }

/* Balance */
.balance-row {
  display: flex;
  align-items: center;
  gap: 20px;
  padding: 10px 12px;
  background: var(--gray-50);
  border-radius: 8px;
  margin-bottom: 12px;
  flex-wrap: wrap;
}
.balance-info { display: flex; flex-direction: column; gap: 1px; }
.balance-label { font-size: 0.7rem; color: var(--gray-500); text-transform: uppercase; letter-spacing: .04em; }
.balance-value { font-size: 0.95rem; font-weight: 700; font-variant-numeric: tabular-nums; }

/* Payment list */
.pay-list { display: flex; flex-direction: column; gap: 6px; }
.pay-item {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 6px 8px;
  border-radius: 6px;
  background: var(--gray-50);
  font-size: 0.82rem;
}
.pay-amount { margin-left: auto; font-weight: 600; font-variant-numeric: tabular-nums; }
.btn-icon-sm {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 20px;
  height: 20px;
  border: none;
  background: transparent;
  cursor: pointer;
  border-radius: 4px;
  padding: 0;
  opacity: 0.5;
}
.btn-icon-sm:hover { opacity: 1; background: var(--gray-200); }

/* Delivery list */
.delivery-list { display: flex; flex-direction: column; gap: 6px; }
.delivery-item {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 6px 8px;
  border-radius: 6px;
  background: var(--gray-50);
  font-size: 0.82rem;
  flex-wrap: wrap;
}

/* Serialized units list (RC-5C) */
.unit-list { display: flex; flex-direction: column; gap: 6px; }
.unit-item {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 6px 8px;
  border-radius: 6px;
  background: var(--gray-50);
  font-size: 0.82rem;
}
.unit-type {
  font-size: 0.68rem;
  font-weight: 700;
  letter-spacing: .04em;
  color: var(--gray-500);
}
.unit-serial {
  font-family: monospace;
  font-size: 0.82rem;
  color: var(--gray-700);
}
.warranty-until {
  font-size: 0.78rem;
  color: var(--gray-500);
}
</style>
