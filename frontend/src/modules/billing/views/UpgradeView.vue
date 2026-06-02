<template>
  <div class="upgrade-page">

    <!-- Header -->
    <div class="page-header">
      <div class="header-top">
        <router-link to="/billing" class="back-link">
          <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
            <path d="M10 12L6 8l4-4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
          Retour à la facturation
        </router-link>
      </div>
      <h2>Choisir un plan</h2>
      <p class="page-subtitle">Sélectionnez le plan adapté à votre activité. Changez de plan à tout moment.</p>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="state-loading">Chargement…</div>

    <!-- Error -->
    <div v-else-if="error" class="state-error">
      <p>{{ error }}</p>
      <button class="btn btn-secondary" @click="fetchSubscription">Réessayer</button>
    </div>

    <!-- Plans grid -->
    <div v-else class="plans-grid">
      <div
        v-for="plan in plans"
        :key="plan.code"
        class="plan-card"
        :class="{
          'plan-card--recommended': plan.recommended,
          'plan-card--current': currentPlanCode === plan.code,
          'plan-card--enterprise': plan.code === 'enterprise',
        }"
      >
        <!-- Badges -->
        <div class="plan-badges">
          <span v-if="plan.recommended" class="badge badge--recommended">Recommande</span>
          <span v-if="currentPlanCode === plan.code" class="badge badge--current">Plan actuel</span>
        </div>

        <!-- Plan header -->
        <div class="plan-header">
          <div class="plan-icon">
            <component :is="plan.icon" />
          </div>
          <h3 class="plan-name">{{ plan.name }}</h3>
          <p class="plan-description">{{ plan.description }}</p>
        </div>

        <!-- Price -->
        <div class="plan-price">
          <template v-if="plan.price !== null">
            <span class="price-amount">{{ formatPrice(plan.price) }}</span>
            <span class="price-period">/mois</span>
          </template>
          <template v-else>
            <span class="price-custom">Sur devis</span>
          </template>
          <p v-if="plan.price === 0" class="price-note">Gratuit pour toujours</p>
          <p v-else-if="plan.price !== null" class="price-note">Facturation mensuelle en XOF</p>
        </div>

        <!-- Quotas -->
        <div class="plan-quotas">
          <div class="quota-item">
            <svg width="14" height="14" viewBox="0 0 16 16" fill="none">
              <path d="M8 1a3 3 0 1 1 0 6 3 3 0 0 1 0-6ZM2 13c0-2.76 2.69-5 6-5s6 2.24 6 5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
            </svg>
            <span>
              <strong>{{ plan.quotas.max_users === null ? 'Illimite' : plan.quotas.max_users }}</strong>
              utilisateur{{ plan.quotas.max_users !== 1 ? 's' : '' }}
            </span>
          </div>
          <div class="quota-item">
            <svg width="14" height="14" viewBox="0 0 16 16" fill="none">
              <rect x="2" y="2" width="5" height="5" rx="1" stroke="currentColor" stroke-width="1.4"/>
              <rect x="9" y="2" width="5" height="5" rx="1" stroke="currentColor" stroke-width="1.4"/>
              <rect x="2" y="9" width="5" height="5" rx="1" stroke="currentColor" stroke-width="1.4"/>
              <rect x="9" y="9" width="5" height="5" rx="1" stroke="currentColor" stroke-width="1.4"/>
            </svg>
            <span>
              <strong>{{ plan.quotas.max_agents === null ? 'Illimite' : plan.quotas.max_agents }}</strong>
              agent{{ plan.quotas.max_agents !== 1 ? 's' : '' }} IA
            </span>
          </div>
          <div class="quota-item">
            <svg width="14" height="14" viewBox="0 0 16 16" fill="none">
              <path d="M2 5h12M2 8h12M2 11h12M5 2v12M11 2v12" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
            </svg>
            <span>
              <strong>{{ plan.quotas.max_warehouses === null ? 'Illimite' : plan.quotas.max_warehouses }}</strong>
              entrepot{{ plan.quotas.max_warehouses !== 1 ? 's' : '' }}
            </span>
          </div>
        </div>

        <!-- Feature list -->
        <ul class="feature-list">
          <li v-for="feature in plan.features" :key="feature" class="feature-item">
            <svg width="14" height="14" viewBox="0 0 16 16" fill="none">
              <path d="M3 8.5L6.5 12 13 5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            {{ feature }}
          </li>
        </ul>

        <!-- CTA -->
        <div class="plan-cta">
          <button
            v-if="currentPlanCode === plan.code"
            class="btn btn-plan btn-plan--current"
            disabled
          >
            Plan actuel
          </button>
          <button
            v-else-if="plan.code === 'enterprise'"
            class="btn btn-plan btn-plan--enterprise"
            @click="chooseEnterprise"
          >
            Nous contacter
          </button>
          <button
            v-else
            class="btn btn-plan"
            :class="plan.recommended ? 'btn-plan--primary' : 'btn-plan--secondary'"
            @click="choosePlan(plan)"
          >
            Choisir ce plan
          </button>
        </div>
      </div>
    </div>

    <!-- Footer note -->
    <p class="upgrade-note">
      Toutes les transactions sont effectuees en XOF (Franc CFA BCEAO).
      Les paiements manuels sont valides sous 24h par notre equipe.
    </p>

  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, h } from 'vue'
import { useRouter } from 'vue-router'
import { authService } from '@/modules/auth/services/authService'
import type { Subscription } from '@/modules/auth/types'

const router = useRouter()

// ── State ──────────────────────────────────────────────────────────────────────
const loading = ref(true)
const error = ref<string | null>(null)
const currentPlanCode = ref<string>('starter')

// ── Icons (inline SVG components) ─────────────────────────────────────────────
const IconStarter = () => h('svg', { width: 28, height: 28, viewBox: '0 0 24 24', fill: 'none' }, [
  h('circle', { cx: 12, cy: 12, r: 9, stroke: 'currentColor', 'stroke-width': 1.6 }),
  h('path', { d: 'M9 12l2 2 4-4', stroke: 'currentColor', 'stroke-width': 1.6, 'stroke-linecap': 'round', 'stroke-linejoin': 'round' }),
])
const IconPro = () => h('svg', { width: 28, height: 28, viewBox: '0 0 24 24', fill: 'none' }, [
  h('polygon', { points: '12,2 15.09,8.26 22,9.27 17,14.14 18.18,21.02 12,17.77 5.82,21.02 7,14.14 2,9.27 8.91,8.26', stroke: 'currentColor', 'stroke-width': 1.6, 'stroke-linecap': 'round', 'stroke-linejoin': 'round' }),
])
const IconEnterprise = () => h('svg', { width: 28, height: 28, viewBox: '0 0 24 24', fill: 'none' }, [
  h('rect', { x: 2, y: 7, width: 20, height: 14, rx: 2, stroke: 'currentColor', 'stroke-width': 1.6 }),
  h('path', { d: 'M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2', stroke: 'currentColor', 'stroke-width': 1.6 }),
  h('line', { x1: 12, y1: 12, x2: 12, y2: 12.01, stroke: 'currentColor', 'stroke-width': 2, 'stroke-linecap': 'round' }),
])

// ── Plans data ─────────────────────────────────────────────────────────────────
const plans = [
  {
    code: 'starter',
    name: 'Starter',
    description: 'Ideal pour demarrer et tester la plateforme.',
    price: 0,
    recommended: false,
    icon: IconStarter,
    quotas: {
      max_users: 2,
      max_agents: 1,
      max_warehouses: 1,
    },
    features: [
      'Catalogue produits (500 refs)',
      'Gestion des commandes',
      'Tableau de bord basique',
      'Import CSV (500 lignes/mois)',
      'Support communaute',
    ],
  },
  {
    code: 'pro',
    name: 'Pro',
    description: 'Pour les PME en croissance avec des besoins avances.',
    price: 15000,
    recommended: true,
    icon: IconPro,
    quotas: {
      max_users: 10,
      max_agents: 5,
      max_warehouses: 3,
    },
    features: [
      'Catalogue illimite',
      'Commandes + livraisons',
      'Rapports avances',
      'Import/Export illimite',
      'Multi-entrepots (3)',
      'Agents IA (5)',
      'Paiements & facturation',
      'Support prioritaire sous 48h',
    ],
  },
  {
    code: 'enterprise',
    name: 'Enterprise',
    description: 'Solution sur-mesure pour les grandes organisations.',
    price: null,
    recommended: false,
    icon: IconEnterprise,
    quotas: {
      max_users: null,
      max_agents: null,
      max_warehouses: null,
    },
    features: [
      'Tout le plan Pro',
      'Utilisateurs illimites',
      'Entrepots illimites',
      'Agents IA illimites',
      'Domaine personnalise',
      'SLA garanti (99.9%)',
      'Integrateur dedié',
      'Onboarding sur site',
      'Support 24/7',
    ],
  },
]

// ── Helpers ────────────────────────────────────────────────────────────────────
function formatPrice(price: number): string {
  return new Intl.NumberFormat('fr-FR', { minimumFractionDigits: 0 }).format(price) + ' XOF'
}

// ── Data fetching ──────────────────────────────────────────────────────────────
async function fetchSubscription(): Promise<void> {
  loading.value = true
  error.value = null
  try {
    const { subscription } = await authService.getSubscription()
    if (subscription) {
      currentPlanCode.value = subscription.plan_code
    }
  } catch (err: any) {
    error.value = err?.response?.data?.message ?? 'Impossible de charger votre abonnement.'
  } finally {
    loading.value = false
  }
}

// ── Actions ────────────────────────────────────────────────────────────────────
function choosePlan(plan: typeof plans[0]): void {
  router.push({ path: '/settings', query: { tab: 'billing', plan: plan.code } })
}

function chooseEnterprise(): void {
  router.push({ path: '/settings', query: { tab: 'billing', plan: 'enterprise' } })
}

onMounted(fetchSubscription)
</script>

<style scoped>
/* ── Page layout ────────────────────────────────────────────────────────────── */
.upgrade-page {
  max-width: 1100px;
  margin: 0 auto;
  padding: 0 0 3rem;
}

.page-header {
  margin-bottom: 2.5rem;
}

.header-top {
  margin-bottom: 1rem;
}

.back-link {
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
  font-size: 0.875rem;
  color: var(--gray-500);
  text-decoration: none;
  transition: color 0.15s;
}
.back-link:hover {
  color: var(--brand-primary);
}

.page-header h2 {
  font-size: 1.75rem;
  font-weight: 700;
  color: var(--gray-900);
  margin: 0 0 0.5rem;
}

.page-subtitle {
  font-size: 0.9375rem;
  color: var(--gray-500);
  margin: 0;
}

/* ── States ─────────────────────────────────────────────────────────────────── */
.state-loading {
  text-align: center;
  padding: 3rem;
  color: var(--gray-400);
  font-size: 0.9375rem;
}

.state-error {
  text-align: center;
  padding: 3rem;
  color: var(--color-error);
}
.state-error p {
  margin-bottom: 1rem;
}

/* ── Plans grid ─────────────────────────────────────────────────────────────── */
.plans-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 1.5rem;
  align-items: start;
}

@media (max-width: 900px) {
  .plans-grid {
    grid-template-columns: 1fr;
    max-width: 480px;
    margin: 0 auto;
  }
}

@media (min-width: 901px) and (max-width: 1100px) {
  .plans-grid {
    gap: 1rem;
  }
}

/* ── Plan card ──────────────────────────────────────────────────────────────── */
.plan-card {
  background: var(--gray-50, #f8fafc);
  border: 1.5px solid var(--gray-200, #e2e8f0);
  border-radius: var(--radius-xl, 16px);
  padding: 1.75rem 1.5rem;
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
  position: relative;
  transition: box-shadow 0.2s, border-color 0.2s;
}
.plan-card:hover {
  box-shadow: var(--shadow-md, 0 4px 16px rgba(0,0,0,0.08));
}

.plan-card--recommended {
  background: #fff;
  border-color: var(--brand-primary, #10b981);
  box-shadow: 0 0 0 3px var(--brand-primary-light, #d1fae5), var(--shadow-md);
}

.plan-card--current {
  border-color: var(--brand-secondary, #3b82f6);
}

.plan-card--enterprise {
  background: var(--gray-800, #1e293b);
  border-color: var(--gray-700, #334155);
  color: #fff;
}
.plan-card--enterprise .plan-description,
.plan-card--enterprise .price-note,
.plan-card--enterprise .quota-item span {
  color: var(--gray-400, #94a3b8);
}
.plan-card--enterprise .feature-item {
  color: var(--gray-300, #cbd5e1);
}
.plan-card--enterprise .feature-item svg,
.plan-card--enterprise .quota-item svg {
  color: var(--gray-400, #94a3b8);
}

/* ── Badges ─────────────────────────────────────────────────────────────────── */
.plan-badges {
  display: flex;
  gap: 0.5rem;
  min-height: 1.5rem;
}

.badge {
  display: inline-flex;
  align-items: center;
  font-size: 0.6875rem;
  font-weight: 600;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  padding: 0.25rem 0.625rem;
  border-radius: 999px;
}

.badge--recommended {
  background: var(--brand-primary-light, #d1fae5);
  color: var(--brand-primary-dark, #059669);
}

.badge--current {
  background: var(--brand-secondary-light, #dbeafe);
  color: var(--brand-secondary-dark, #2563eb);
}

/* ── Plan header ────────────────────────────────────────────────────────────── */
.plan-header {
  display: flex;
  flex-direction: column;
  gap: 0.375rem;
}

.plan-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 48px;
  height: 48px;
  border-radius: var(--radius-md, 8px);
  background: var(--brand-primary-bg, #ecfdf5);
  color: var(--brand-primary, #10b981);
  margin-bottom: 0.25rem;
}
.plan-card--recommended .plan-icon {
  background: var(--brand-primary-bg, #ecfdf5);
  color: var(--brand-primary, #10b981);
}
.plan-card--enterprise .plan-icon {
  background: var(--gray-700, #334155);
  color: var(--gray-300, #cbd5e1);
}

.plan-name {
  font-size: 1.25rem;
  font-weight: 700;
  margin: 0;
}

.plan-description {
  font-size: 0.875rem;
  color: var(--gray-500, #64748b);
  margin: 0;
  line-height: 1.5;
}

/* ── Price ──────────────────────────────────────────────────────────────────── */
.plan-price {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  gap: 0.25rem;
  row-gap: 0.125rem;
}

.price-amount {
  font-size: 1.75rem;
  font-weight: 800;
  letter-spacing: -0.02em;
  color: var(--gray-900, #0f172a);
}
.plan-card--enterprise .price-amount {
  color: #fff;
}

.price-period {
  font-size: 0.875rem;
  color: var(--gray-500, #64748b);
  font-weight: 500;
}

.price-custom {
  font-size: 1.5rem;
  font-weight: 700;
  color: var(--gray-300, #cbd5e1);
}

.price-note {
  width: 100%;
  font-size: 0.75rem;
  color: var(--gray-400, #94a3b8);
  margin: 0.125rem 0 0;
}

/* ── Quotas ─────────────────────────────────────────────────────────────────── */
.plan-quotas {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  padding: 0.875rem 1rem;
  background: rgba(0,0,0,0.03);
  border-radius: var(--radius-md, 8px);
}
.plan-card--enterprise .plan-quotas {
  background: rgba(255,255,255,0.05);
}

.quota-item {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.875rem;
  color: var(--gray-700, #334155);
}
.quota-item svg {
  flex-shrink: 0;
  color: var(--gray-400, #94a3b8);
}
.quota-item strong {
  font-weight: 600;
}

/* ── Feature list ───────────────────────────────────────────────────────────── */
.feature-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  flex: 1;
}

.feature-item {
  display: flex;
  align-items: flex-start;
  gap: 0.5rem;
  font-size: 0.875rem;
  color: var(--gray-700, #334155);
  line-height: 1.45;
}
.feature-item svg {
  flex-shrink: 0;
  margin-top: 0.1rem;
  color: var(--brand-primary, #10b981);
}

/* ── CTA buttons ────────────────────────────────────────────────────────────── */
.plan-cta {
  margin-top: auto;
  padding-top: 0.5rem;
}

.btn-plan {
  width: 100%;
  padding: 0.6875rem 1rem;
  border-radius: var(--radius-md, 8px);
  font-size: 0.9375rem;
  font-weight: 600;
  border: none;
  cursor: pointer;
  transition: background 0.15s, opacity 0.15s, box-shadow 0.15s;
  line-height: 1;
}

.btn-plan--primary {
  background: var(--brand-primary, #10b981);
  color: #fff;
}
.btn-plan--primary:hover {
  background: var(--brand-primary-dark, #059669);
  box-shadow: 0 2px 8px rgba(16,185,129,0.35);
}

.btn-plan--secondary {
  background: var(--gray-100, #f1f5f9);
  color: var(--gray-700, #334155);
  border: 1.5px solid var(--gray-200, #e2e8f0);
}
.btn-plan--secondary:hover {
  background: var(--gray-200, #e2e8f0);
}

.btn-plan--current {
  background: var(--brand-secondary-light, #dbeafe);
  color: var(--brand-secondary, #3b82f6);
  cursor: default;
  opacity: 0.8;
}

.btn-plan--enterprise {
  background: var(--brand-primary, #10b981);
  color: #fff;
}
.btn-plan--enterprise:hover {
  background: var(--brand-primary-dark, #059669);
}

/* ── Footer note ────────────────────────────────────────────────────────────── */
.upgrade-note {
  margin-top: 2rem;
  text-align: center;
  font-size: 0.8125rem;
  color: var(--gray-400, #94a3b8);
  line-height: 1.6;
}
</style>
