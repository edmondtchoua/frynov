<template>
  <div style="max-width: 820px;">
    <!-- Header -->
    <div class="page-header">
      <div>
        <RouterLink to="/orders" class="back-link">← Commandes</RouterLink>
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
      <h3>Erreur</h3>
      <p>{{ error }}</p>
    </div>

    <template v-else-if="order">
      <!-- ── Order lines ──────────────────────────────────────────────────── -->
      <div class="card" style="padding: 0; overflow: hidden; margin-bottom: 16px;">
        <table class="data-table">
          <thead>
            <tr>
              <th>SKU</th>
              <th>Produit</th>
              <th style="text-align:right;">Qté</th>
              <th style="text-align:right;" class="hide-mobile">Prix unit.</th>
              <th style="text-align:right;">Sous-total</th>
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
              <td colspan="3" class="hide-mobile" style="text-align: right; font-weight: 700; padding: 10px 16px;">Total</td>
              <td class="show-mobile-only" style="text-align: right; font-weight: 700; padding: 10px 16px;">Total</td>
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
            <div v-if="order.note" style="margin-bottom: 8px;"><strong>Note :</strong> {{ order.note }}</div>
            <div><strong>Créée le :</strong> {{ fmtDate(order.created_at) }}</div>
            <div v-if="order.fulfilled_at" style="margin-top: 4px;"><strong>Livrée le :</strong> {{ fmtDate(order.fulfilled_at) }}</div>
            <div v-if="order.cancelled_at" style="margin-top: 4px;"><strong>Annulée le :</strong> {{ fmtDate(order.cancelled_at) }}</div>
          </div>

          <!-- ── Payments panel ────────────────────────────────────────────── -->
          <div class="card">
            <div class="panel-header">
              <h4 class="panel-title">Paiements</h4>
              <button class="btn btn-ghost btn-sm" @click="openPaymentModal">
                <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                  <path d="M7 2v10M2 7h10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                </svg>
                Enregistrer
              </button>
            </div>

            <!-- Balance summary -->
            <div class="balance-row">
              <div class="balance-info">
                <span class="balance-label">Encaissé</span>
                <span class="balance-value" :style="payBalance >= order.total_amount ? 'color:#059669;' : ''">
                  {{ fmt(payBalance) }}
                </span>
              </div>
              <div class="balance-info">
                <span class="balance-label">Total</span>
                <span class="balance-value">{{ fmt(order.total_amount) }}</span>
              </div>
              <div class="balance-info">
                <span class="balance-label">Reste</span>
                <span class="balance-value" :style="payBalance < order.total_amount ? 'color:#b45309;' : 'color:#059669;'">
                  {{ fmt(Math.max(0, order.total_amount - payBalance)) }}
                </span>
              </div>
              <span v-if="payIsFullyPaid" class="badge badge-success" style="align-self: center;">Soldé ✓</span>
              <span v-else class="badge badge-warning" style="align-self: center;">Partiel</span>
            </div>

            <!-- Payment list -->
            <div v-if="payLoading" class="loading-center" style="min-height: 60px; padding: 12px;">
              <span class="spinner-sm"></span>
            </div>
            <div v-else-if="payments.length === 0" style="font-size: 0.85rem; color: var(--gray-400); padding: 8px 0;">
              Aucun paiement enregistré.
            </div>
            <div v-else class="pay-list">
              <div v-for="p in payments" :key="p.id" class="pay-item">
                <span :class="`badge ${methodBadge(p.method)}`" style="font-size: 0.72rem;">{{ methodLabel(p.method) }}</span>
                <span style="font-size: 0.85rem; color: var(--gray-600);">{{ fmtDateShort(p.paid_at) }}</span>
                <span v-if="p.reference" style="font-family: monospace; font-size: 0.78rem; color: var(--gray-500);">{{ p.reference }}</span>
                <span class="pay-amount">{{ fmt(p.amount_cents) }}</span>
                <button class="btn-icon-sm" title="Annuler" @click="voidPayment(p.id)">
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
              <h4 class="panel-title">Livraison</h4>
              <RouterLink to="/deliveries" class="btn btn-ghost btn-sm">Gérer →</RouterLink>
            </div>

            <div v-if="deliveryLoading" class="loading-center" style="min-height: 60px; padding: 12px;">
              <span class="spinner-sm"></span>
            </div>
            <div v-else-if="deliveries.length === 0" style="font-size: 0.85rem; color: var(--gray-400); padding: 8px 0;">
              Aucune livraison associée à cette commande.
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
        </div>

        <!-- Right column — actions -->
        <div style="display: flex; flex-direction: column; gap: 12px;">
          <div class="card">
            <h4 class="panel-title" style="margin-bottom: 12px;">Actions</h4>
            <div style="display: flex; flex-direction: column; gap: 8px;">
              <button
                v-if="order.status === 'draft'"
                class="btn btn-primary"
                :disabled="actionLoading !== false"
                @click="act('confirm')"
              >
                <span v-if="actionLoading === 'confirm'" class="spinner-sm"></span>
                Confirmer la commande
              </button>

              <button
                v-if="order.status === 'confirmed'"
                class="btn btn-primary"
                :disabled="actionLoading !== false"
                @click="act('fulfill')"
              >
                <span v-if="actionLoading === 'fulfill'" class="spinner-sm"></span>
                Marquer livrée
              </button>

              <button
                v-if="order.status === 'draft' || order.status === 'confirmed'"
                class="btn btn-danger"
                :disabled="actionLoading !== false"
                @click="act('cancel')"
              >
                <span v-if="actionLoading === 'cancel'" class="spinner-sm"></span>
                Annuler
              </button>

              <RouterLink :to="`/inventory/movements/${order.lines[0]?.product_id}`" class="btn btn-ghost btn-sm" v-if="order.lines.length > 0">
                Voir le stock →
              </RouterLink>
            </div>

            <div v-if="actionError" class="form-error" style="margin-top: 12px;">{{ actionError }}</div>
          </div>
        </div>
      </div>
    </template>

    <!-- ── Record payment modal (shared BaseModal — UX-03) ───────────────────── -->
    <BaseModal v-model="payModal.open" title="Enregistrer un paiement">
      <div style="display: flex; flex-direction: column; gap: 14px;">
        <p style="font-size: 0.875rem; color: var(--gray-500); margin: 0;">
          Commande {{ order?.number }} · Reste {{ fmt(Math.max(0, (order?.total_amount ?? 0) - payBalance)) }}
        </p>
        <div class="form-group">
          <label class="form-label">Montant <span style="color:#dc2626;">*</span></label>
          <div style="display: flex; gap: 8px;">
            <input
              v-model.number="payForm.amount"
              type="number" min="0" step="0.01"
              class="form-input" style="flex:1;"
              placeholder="0"
            />
            <!-- Currency locked to the order's: the balance sums centimes across
                 payments without converting, so mixing currencies would corrupt it. -->
            <input
              :value="payForm.currency"
              class="form-input" style="width: 90px; background:var(--gray-50); text-align:center;"
              readonly tabindex="-1" title="Devise de la commande"
            />
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Moyen <span style="color:#dc2626;">*</span></label>
          <select v-model="payForm.method" class="form-input">
            <option value="cash">Espèces</option>
            <option value="mobile_money">Mobile Money</option>
            <option value="card">Carte</option>
            <option value="transfer">Virement</option>
            <option value="cheque">Chèque</option>
          </select>
        </div>

        <div class="form-group">
          <label class="form-label">Référence</label>
          <input v-model="payForm.reference" type="text" class="form-input" placeholder="N° reçu…" />
        </div>

        <p v-if="payModal.error" style="color:#dc2626; font-size:0.875rem;">{{ payModal.error }}</p>
      </div>

      <template #footer>
        <button class="btn btn-ghost" @click="payModal.open = false">Annuler</button>
        <button
          class="btn btn-primary"
          :disabled="payModal.saving || !payForm.amount || payForm.amount <= 0"
          @click="submitPayment"
        >
          <span v-if="payModal.saving" class="spinner-sm"></span>
          Enregistrer
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
import BaseModal from '@/shared/ui/BaseModal.vue'
import type { Order } from '../types'
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
const payForm      = reactive({ amount: undefined as number | undefined, currency: 'XOF', method: 'cash' as PaymentMethod, reference: '' })

// ── Deliveries ─────────────────────────────────────────────────────────────────
const deliveries      = ref<Delivery[]>([])
const deliveryLoading = ref(false)

// ── Load ───────────────────────────────────────────────────────────────────────
async function load() {
  loading.value = true
  error.value   = null
  try {
    order.value = await orderService.get(id)
  } catch {
    error.value = 'Commande introuvable.'
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

// ── Order actions ──────────────────────────────────────────────────────────────
async function act(action: 'confirm' | 'fulfill' | 'cancel') {
  actionLoading.value = action
  actionError.value   = null
  try {
    order.value = await orderService[action](id)
  } catch (e: any) {
    actionError.value = e?.response?.data?.message ?? `Erreur lors de l'action "${action}".`
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
    await paymentService.record({
      order_id:     id,
      amount_cents: Math.round(payForm.amount * 100),
      currency:     payForm.currency,
      method:       payForm.method,
      reference:    payForm.reference || undefined,
    })
    payModal.open = false
    loadPayments()
  } catch (e: any) {
    payModal.error = e?.response?.data?.message ?? 'Une erreur est survenue.'
  } finally {
    payModal.saving = false
  }
}

async function voidPayment(paymentId: string) {
  if (!confirm('Annuler ce paiement ?')) return
  payActionError.value = null
  try {
    await paymentService.void(paymentId)
    loadPayments()
  } catch (e: any) {
    payActionError.value = e?.response?.data?.message ?? "Impossible d'annuler ce paiement."
  }
}

// ── Formatters ────────────────────────────────────────────────────────────────
function statusLabel(s: string) {
  return { draft: 'Brouillon', confirmed: 'Confirmée', fulfilled: 'Livrée', cancelled: 'Annulée' }[s] ?? s
}
function statusBadge(s: string) {
  return { draft: 'badge-gray', confirmed: 'badge-blue', fulfilled: 'badge-success', cancelled: 'badge-error' }[s] ?? ''
}
function methodLabel(m: PaymentMethod): string {
  return ({ cash: 'Espèces', mobile_money: 'Mobile Money', card: 'Carte', transfer: 'Virement', cheque: 'Chèque' } as Record<PaymentMethod, string>)[m] ?? m
}
function methodBadge(m: PaymentMethod): string {
  return ({ cash: 'badge-success', mobile_money: 'badge-blue', card: 'badge-blue', transfer: 'badge-gray', cheque: 'badge-gray' } as Record<PaymentMethod, string>)[m] ?? 'badge-gray'
}
function deliveryStatusLabel(s: DeliveryStatus): string {
  return ({ pending: 'En attente', dispatched: 'Expédiée', in_transit: 'En transit', delivered: 'Livrée', failed: 'Échec' } as Record<DeliveryStatus, string>)[s] ?? s
}
function deliveryStatusBadge(s: DeliveryStatus): string {
  return ({ pending: 'badge-gray', dispatched: 'badge-blue', in_transit: 'badge-blue', delivered: 'badge-success', failed: 'badge-error' } as Record<DeliveryStatus, string>)[s] ?? 'badge-gray'
}
function fmt(cents: number) {
  return formatMoney(cents, order.value?.currency ?? 'XOF')
}
const fmtDate = formatDateTime
const fmtDateShort = formatDateShort

onMounted(() => { load(); loadPayments(); loadDeliveries() })
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
</style>
