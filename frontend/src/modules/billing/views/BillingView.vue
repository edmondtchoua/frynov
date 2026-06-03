<template>
  <div>

    <div class="page-header">
      <h2>Abonnement &amp; Facturation</h2>
      <p class="page-subtitle">Gérez votre plan et consultez vos paiements</p>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="loading-center" style="min-height:220px">
      <span class="spinner-sm"></span>
    </div>

    <template v-else>

      <!-- Plan card -->
      <div class="card plan-card">
        <div class="plan-card-header">
          <div class="plan-identity">
            <div class="plan-icon-wrap">
              <svg width="22" height="22" viewBox="0 0 22 22" fill="none">
                <path d="M11 2L20 7V15L11 20L2 15V7L11 2Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>
                <path d="M11 11L20 7M11 11L2 7M11 11V20" stroke="currentColor" stroke-width="1.6"/>
              </svg>
            </div>
            <div>
              <div class="plan-name">{{ subscription?.plan_name ?? 'Plan inconnu' }}</div>
              <div class="plan-code text-muted">{{ subscription?.plan_code ?? '—' }}</div>
            </div>
          </div>
          <span :class="statusBadgeClass">{{ statusLabel }}</span>
        </div>

        <div class="plan-details-grid">
          <div class="plan-detail-item">
            <span class="plan-detail-label">Prix mensuel</span>
            <span class="plan-detail-value">
              {{ subscription?.plan_price_monthly != null
                  ? formatMoney(subscription.plan_price_monthly, subscription.currency ?? 'XOF')
                  : '—' }}
            </span>
          </div>
          <div class="plan-detail-item">
            <span class="plan-detail-label">Prix annuel</span>
            <span class="plan-detail-value">
              {{ subscription?.plan_price_yearly != null
                  ? formatMoney(subscription.plan_price_yearly, subscription.currency ?? 'XOF')
                  : '—' }}
            </span>
          </div>
          <div class="plan-detail-item" v-if="subscription?.trial_ends_at">
            <span class="plan-detail-label">Fin d'essai</span>
            <span class="plan-detail-value">{{ formatDate(subscription.trial_ends_at) }}</span>
          </div>
          <div class="plan-detail-item" v-if="subscription?.current_period_end">
            <span class="plan-detail-label">Renouvellement</span>
            <span class="plan-detail-value">{{ formatDate(subscription.current_period_end) }}</span>
          </div>
        </div>

        <!-- Trial countdown -->
        <div v-if="trialDaysLeft !== null" class="trial-banner">
          <svg width="15" height="15" viewBox="0 0 16 16" fill="none">
            <circle cx="8" cy="8" r="7" stroke="currentColor" stroke-width="1.5"/>
            <path d="M8 5v3.5L10 10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
          </svg>
          Période d'essai — <strong>{{ trialDaysLeft }} jour{{ trialDaysLeft > 1 ? 's' : '' }}</strong> restant{{ trialDaysLeft > 1 ? 's' : '' }}
        </div>

        <!-- Suspended warning -->
        <div v-else-if="subscription?.status === 'suspended'" class="suspended-banner">
          <svg width="15" height="15" viewBox="0 0 16 16" fill="none">
            <path d="M8 2L14 13H2L8 2Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
            <path d="M8 7v3M8 11.5v.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
          </svg>
          Votre compte est suspendu. Effectuez un paiement pour réactiver l'accès.
        </div>

        <div class="plan-actions">
          <RouterLink to="/billing/upgrade" class="btn btn-primary">
            <svg width="14" height="14" viewBox="0 0 14 14" fill="none" style="margin-right:6px">
              <path d="M7 2L11 6H8.5V12H5.5V6H3L7 2Z" fill="currentColor"/>
            </svg>
            Mettre à niveau
          </RouterLink>
          <RouterLink to="/settings" class="btn btn-secondary">
            Payer manuellement
          </RouterLink>
        </div>
      </div>

      <!-- Usage section -->
      <div class="card" style="margin-top:1rem">
        <h3 class="section-title">Utilisation</h3>

        <div v-if="!subscription" class="empty-state" style="padding:1rem 0">
          <p>Aucune information de quota disponible.</p>
        </div>

        <div v-else class="usage-list">

          <!-- Users -->
          <div class="usage-item">
            <div class="usage-header">
              <span class="usage-label">
                <svg width="14" height="14" viewBox="0 0 16 16" fill="none" style="margin-right:5px;vertical-align:-2px">
                  <circle cx="8" cy="5" r="3" stroke="currentColor" stroke-width="1.4"/>
                  <path d="M2 14c0-3.314 2.686-5 6-5s6 1.686 6 5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
                </svg>
                Utilisateurs
              </span>
              <span class="usage-count">
                {{ usageData.users.used }}
                <span class="text-muted">/ {{ subscription.max_users != null ? subscription.max_users : '∞' }}</span>
              </span>
            </div>
            <div class="progress-track">
              <div
                class="progress-fill"
                :class="usageColor(usagePercent(usageData.users.used, subscription.max_users))"
                :style="{ width: usagePercent(usageData.users.used, subscription.max_users) + '%' }"
              ></div>
            </div>
          </div>

          <!-- Products -->
          <div class="usage-item">
            <div class="usage-header">
              <span class="usage-label">
                <svg width="14" height="14" viewBox="0 0 16 16" fill="none" style="margin-right:5px;vertical-align:-2px">
                  <path d="M8 1L14 4.5V11.5L8 15L2 11.5V4.5L8 1Z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/>
                  <path d="M8 8L14 4.5M8 8L2 4.5M8 8V15" stroke="currentColor" stroke-width="1.4"/>
                </svg>
                Produits
              </span>
              <span class="usage-count">
                {{ usageData.products.used }}
                <span class="text-muted">/ {{ subscription.max_products != null ? subscription.max_products : '∞' }}</span>
              </span>
            </div>
            <div class="progress-track">
              <div
                class="progress-fill"
                :class="usageColor(usagePercent(usageData.products.used, subscription.max_products))"
                :style="{ width: usagePercent(usageData.products.used, subscription.max_products) + '%' }"
              ></div>
            </div>
          </div>

          <!-- Orders -->
          <div class="usage-item">
            <div class="usage-header">
              <span class="usage-label">
                <svg width="14" height="14" viewBox="0 0 16 16" fill="none" style="margin-right:5px;vertical-align:-2px">
                  <path d="M4 4h8a1 1 0 011 1v8a1 1 0 01-1 1H4a1 1 0 01-1-1V5a1 1 0 011-1z" stroke="currentColor" stroke-width="1.4"/>
                  <path d="M5 8h6M5 11h3" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
                </svg>
                Commandes ce mois
              </span>
              <span class="usage-count">
                {{ usageData.orders.used }}
                <span class="text-muted">/ {{ subscription.max_monthly_orders != null ? subscription.max_monthly_orders : '∞' }}</span>
              </span>
            </div>
            <div class="progress-track">
              <div
                class="progress-fill"
                :class="usageColor(usagePercent(usageData.orders.used, subscription.max_monthly_orders))"
                :style="{ width: usagePercent(usageData.orders.used, subscription.max_monthly_orders) + '%' }"
              ></div>
            </div>
          </div>

        </div>
      </div>

      <!-- Manual payments table -->
      <div class="card" style="margin-top:1rem">
        <div class="section-header-row">
          <h3 class="section-title" style="margin-bottom:0">Paiements manuels</h3>
          <RouterLink to="/settings" class="btn-link">
            Soumettre un paiement →
          </RouterLink>
        </div>

        <div v-if="paymentsLoading" class="loading-center" style="min-height:80px">
          <span class="spinner-sm"></span>
        </div>

        <div v-else-if="!payments.length" class="empty-state" style="padding:1.5rem 0">
          <svg width="36" height="36" viewBox="0 0 36 36" fill="none" class="empty-icon">
            <rect x="6" y="8" width="24" height="3" rx="1.5" fill="var(--gray-200)"/>
            <rect x="6" y="15" width="18" height="3" rx="1.5" fill="var(--gray-200)"/>
            <rect x="6" y="22" width="20" height="3" rx="1.5" fill="var(--gray-200)"/>
          </svg>
          <p>Aucun paiement manuel enregistré.</p>
        </div>

        <div v-else class="table-wrap">
          <table class="data-table">
            <thead>
              <tr>
                <th>Date</th>
                <th>Montant</th>
                <th>Méthode</th>
                <th>Statut</th>
                <th>Notes</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="p in payments" :key="p.id">
                <td class="text-muted">{{ formatDate(p.created_at) }}</td>
                <td style="font-variant-numeric:tabular-nums;font-weight:600">
                  {{ formatMoney(p.amount_cents, p.currency) }}
                </td>
                <td>{{ paymentMethodLabel(p.payment_method) }}</td>
                <td><span :class="paymentBadgeClass(p.status)">{{ paymentStatusLabel(p.status) }}</span></td>
                <td class="text-muted">{{ p.notes ?? '—' }}</td>
              </tr>
            </tbody>
          </table>
        </div>

      </div>

    </template>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { formatMoney } from '@/shared/utils/money'
import api from '@/services/api'
import type { Subscription } from '@/modules/auth/types'

// ── Types ─────────────────────────────────────────────────────────────────────

interface ManualPayment {
  id: string
  amount_cents: number
  currency: string
  payment_method: string
  status: 'pending' | 'approved' | 'rejected'
  notes: string | null
  created_at: string | null
  rejection_reason: string | null
}

interface SubscriptionResponse {
  subscription: (Subscription & {
    usage?: {
      users?: number
      products?: number
      orders?: number
    }
  }) | null
}

// ── State ─────────────────────────────────────────────────────────────────────

const loading         = ref(true)
const paymentsLoading = ref(true)
const subscription    = ref<SubscriptionResponse['subscription']>(null)
const payments        = ref<ManualPayment[]>([])

// Fallback usage — may come from subscription.usage if the API provides it
const usageData = computed(() => ({
  users:    { used: subscription.value?.usage?.users    ?? 0 },
  products: { used: subscription.value?.usage?.products ?? 0 },
  orders:   { used: subscription.value?.usage?.orders   ?? 0 },
}))

// ── Derived ───────────────────────────────────────────────────────────────────

const trialDaysLeft = computed(() => {
  const end = subscription.value?.trial_ends_at
  if (!end || subscription.value?.status !== 'trialing') return null
  const diff = Math.ceil((new Date(end).getTime() - Date.now()) / 86_400_000)
  return diff > 0 ? diff : null
})

const statusLabel = computed(() => {
  const map: Record<string, string> = {
    trialing:         'Essai',
    active:           'Actif',
    suspended:        'Suspendu',
    cancelled:        'Annulé',
    pending_approval: 'En attente',
  }
  return map[subscription.value?.status ?? ''] ?? (subscription.value?.status ?? '—')
})

const statusBadgeClass = computed(() => {
  const map: Record<string, string> = {
    trialing:         'badge badge-warning',
    active:           'badge badge-success',
    suspended:        'badge badge-error',
    cancelled:        'badge badge-gray',
    pending_approval: 'badge badge-blue',
  }
  return map[subscription.value?.status ?? ''] ?? 'badge badge-gray'
})

// ── Fetch ─────────────────────────────────────────────────────────────────────

onMounted(async () => {
  await Promise.all([
    api.get<SubscriptionResponse>('/api/me/subscription')
      .then(r => { subscription.value = r.data.subscription })
      .catch(() => {})
      .finally(() => { loading.value = false }),

    api.get<{ data: ManualPayment[] }>('/api/me/manual-payments')
      .then(r => { payments.value = r.data.data ?? [] })
      .catch(() => {})
      .finally(() => { paymentsLoading.value = false }),
  ])
})

// ── Formatters ────────────────────────────────────────────────────────────────

function formatDate(iso: string | null): string {
  if (!iso) return '—'
  try {
    return new Intl.DateTimeFormat('fr-FR', { day: '2-digit', month: 'short', year: 'numeric' }).format(new Date(iso))
  } catch {
    return iso
  }
}

// ── Usage helpers ─────────────────────────────────────────────────────────────

function usagePercent(used: number, max: number | null | undefined): number {
  if (!max) return 0
  return Math.min(Math.round((used / max) * 100), 100)
}

function usageColor(pct: number): string {
  if (pct >= 90) return 'fill-error'
  if (pct >= 70) return 'fill-warning'
  return 'fill-primary'
}

// ── Payment helpers ───────────────────────────────────────────────────────────

function paymentMethodLabel(method: string): string {
  const map: Record<string, string> = {
    mobile_money: 'Mobile Money',
    bank_transfer: 'Virement bancaire',
    cash: 'Espèces',
    check: 'Chèque',
  }
  return map[method] ?? method
}

function paymentStatusLabel(status: string): string {
  const map: Record<string, string> = {
    pending:  'En attente',
    approved: 'Approuvé',
    rejected: 'Rejeté',
  }
  return map[status] ?? status
}

function paymentBadgeClass(status: string): string {
  const map: Record<string, string> = {
    pending:  'badge badge-warning',
    approved: 'badge badge-success',
    rejected: 'badge badge-error',
  }
  return map[status] ?? 'badge badge-gray'
}
</script>

<style scoped>
.page-subtitle {
  color: var(--gray-500);
  font-size: var(--text-sm);
  margin-top: 0.25rem;
}

/* ── Plan card ───────────────────────────────────────────────────────────── */
.plan-card {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.plan-card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
}

.plan-identity {
  display: flex;
  align-items: center;
  gap: 0.875rem;
}

.plan-icon-wrap {
  width: 44px;
  height: 44px;
  border-radius: var(--radius-md);
  background: var(--brand-primary-bg);
  color: var(--brand-primary-dark);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.plan-name {
  font-size: var(--text-lg);
  font-weight: 700;
  color: var(--gray-900);
  line-height: 1.2;
}

.plan-code {
  font-size: var(--text-xs);
  margin-top: 2px;
}

.plan-details-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
  gap: 0.75rem;
  padding: 1rem;
  background: var(--gray-50);
  border-radius: var(--radius-md);
  border: 1px solid var(--gray-100);
}

.plan-detail-item {
  display: flex;
  flex-direction: column;
  gap: 3px;
}

.plan-detail-label {
  font-size: var(--text-xs);
  color: var(--gray-500);
  font-weight: 500;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.plan-detail-value {
  font-size: var(--text-sm);
  font-weight: 600;
  color: var(--gray-900);
}

/* ── Banners ─────────────────────────────────────────────────────────────── */
.trial-banner {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  background: #fffbeb;
  border: 1px solid #fcd34d;
  color: #92400e;
  border-radius: var(--radius-md);
  padding: 0.625rem 1rem;
  font-size: var(--text-sm);
  font-weight: 500;
}

.suspended-banner {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  background: #fff1f2;
  border: 1px solid #fca5a5;
  color: #991b1b;
  border-radius: var(--radius-md);
  padding: 0.625rem 1rem;
  font-size: var(--text-sm);
  font-weight: 500;
}

/* ── Plan actions ────────────────────────────────────────────────────────── */
.plan-actions {
  display: flex;
  gap: 0.75rem;
  flex-wrap: wrap;
}

/* ── Section common ──────────────────────────────────────────────────────── */
.section-title {
  font-size: var(--text-base);
  font-weight: 600;
  color: var(--gray-900);
  margin: 0 0 1rem 0;
}

.section-header-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 1rem;
}

.btn-link {
  font-size: var(--text-sm);
  color: var(--brand-secondary);
  font-weight: 500;
  text-decoration: none;
  transition: color 0.15s;
}
.btn-link:hover { text-decoration: underline; }

/* ── Usage bars ──────────────────────────────────────────────────────────── */
.usage-list {
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
}

.usage-item {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.usage-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.usage-label {
  font-size: var(--text-sm);
  font-weight: 500;
  color: var(--gray-700);
  display: flex;
  align-items: center;
}

.usage-count {
  font-size: var(--text-sm);
  font-weight: 600;
  color: var(--gray-900);
}

.progress-track {
  height: 8px;
  border-radius: var(--radius-full);
  background: var(--gray-100);
  overflow: hidden;
}

.progress-fill {
  height: 100%;
  border-radius: var(--radius-full);
  transition: width 0.4s ease;
}

.fill-primary { background: var(--brand-primary); }
.fill-warning  { background: #f59e0b; }
.fill-error    { background: var(--color-error); }

/* ── Table ───────────────────────────────────────────────────────────────── */
.table-wrap {
  overflow-x: auto;
}

/* ── Empty state ─────────────────────────────────────────────────────────── */
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  color: var(--gray-400);
  font-size: var(--text-sm);
}

.empty-icon {
  opacity: 0.6;
}

/* ── Misc ────────────────────────────────────────────────────────────────── */
.text-muted { color: var(--gray-500); }
</style>
