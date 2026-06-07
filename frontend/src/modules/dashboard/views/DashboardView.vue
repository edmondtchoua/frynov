<template>
  <div>

    <div class="page-header">
      <h2>Tableau de bord</h2>
      <p class="page-subtitle">Vue d'ensemble de votre activité</p>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="loading-center" style="min-height:200px">
      <span class="spinner-sm"></span>
    </div>

    <template v-else>

      <!-- KPI cards -->
      <div class="kpi-grid">
        <div class="kpi-card" v-for="kpi in kpis" :key="kpi.label">
          <div class="kpi-icon-wrap" :class="`kpi-icon--${kpi.color}`">
            <component :is="kpi.icon" />
          </div>
          <div class="kpi-body">
            <div class="kpi-value">{{ kpi.value }}</div>
            <div class="kpi-label">{{ kpi.label }}</div>
            <div v-if="kpi.change !== null && kpi.change !== undefined" class="kpi-trend" :class="kpi.change >= 0 ? 'trend-up' : 'trend-down'">
              <svg v-if="kpi.change >= 0" width="12" height="12" viewBox="0 0 12 12" fill="none">
                <path d="M6 2L10 6H7V10H5V6H2L6 2Z" fill="currentColor"/>
              </svg>
              <svg v-else width="12" height="12" viewBox="0 0 12 12" fill="none">
                <path d="M6 10L2 6H5V2H7V6H10L6 10Z" fill="currentColor"/>
              </svg>
              {{ Math.abs(kpi.change) }}% vs hier
            </div>
          </div>
        </div>
      </div>

      <!-- Charts row -->
      <div class="charts-row">

        <!-- Revenue bar chart -->
        <div class="card chart-card">
          <div class="chart-card-header">
            <h3>Chiffre d'affaires</h3>
            <RouterLink to="/reports/sales" class="chart-card-link">Détail →</RouterLink>
          </div>
          <RevenueBarChart :points="dashboardData?.revenue_chart ?? []" />
        </div>

        <!-- Recent orders -->
        <div class="card chart-card">
          <div class="chart-card-header">
            <h3>Commandes récentes</h3>
            <RouterLink to="/orders" class="chart-card-link">Voir tout →</RouterLink>
          </div>
          <div v-if="!dashboardData?.recent_orders?.length" class="chart-placeholder">
            <svg width="40" height="40" viewBox="0 0 40 40" fill="none" class="chart-placeholder-icon">
              <rect x="4" y="8" width="32" height="4" rx="2" fill="var(--brand-secondary)" opacity="0.3"/>
              <rect x="4" y="17" width="24" height="4" rx="2" fill="var(--brand-secondary)" opacity="0.5"/>
              <rect x="4" y="26" width="28" height="4" rx="2" fill="var(--brand-secondary)" opacity="0.7"/>
            </svg>
            <p>Aucune commande pour l'instant.</p>
          </div>
          <table v-else class="data-table" style="margin-top:.5rem">
            <thead>
              <tr>
                <th>N°</th>
                <th>Client</th>
                <th>Statut</th>
                <th style="text-align:right">Montant</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="o in dashboardData!.recent_orders" :key="o.id">
                <td><RouterLink :to="`/orders/${o.id}`" class="link-blue">{{ o.number }}</RouterLink></td>
                <td class="text-muted">{{ o.customer?.name ?? '—' }}</td>
                <td><span :class="orderBadge(o.status)">{{ orderLabel(o.status) }}</span></td>
                <td style="text-align:right;font-variant-numeric:tabular-nums">{{ formatMoneyCompact(o.total_amount) }}</td>
              </tr>
            </tbody>
          </table>
        </div>

      </div>

      <!-- Top products -->
      <div v-if="dashboardData?.top_products?.length" class="card" style="margin-top:1rem">
        <div class="chart-card-header" style="margin-bottom:.75rem">
          <h3>Top produits — 30 derniers jours</h3>
          <RouterLink to="/reports/sales" class="chart-card-link">Rapport ventes →</RouterLink>
        </div>
        <div class="top-products-list">
          <div v-for="(p, idx) in dashboardData!.top_products" :key="p.product_id ?? idx" class="top-product-row">
            <span class="top-rank">{{ idx + 1 }}</span>
            <div class="top-product-info">
              <span class="top-product-name">{{ p.product_name }}</span>
              <span class="top-product-sku text-muted">{{ p.sku }}</span>
            </div>
            <div class="top-product-stats">
              <span class="top-product-qty">{{ p.total_qty }} unités</span>
              <span class="top-product-revenue">{{ formatMoneyCompact(p.total_revenue) }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- ── Modules section ─────────────────────────────────────── -->
      <div class="card modules-section" style="margin-top:1.5rem">
        <div class="chart-card-header" style="margin-bottom:1rem">
          <h3>Mes modules</h3>
          <RouterLink to="/settings" class="chart-card-link">Gérer l'abonnement →</RouterLink>
        </div>

        <!-- Trial banner -->
        <div v-if="subscriptionStatus === 'trialing' && trialDaysLeft !== null" class="trial-banner">
          <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="7" stroke="currentColor" stroke-width="1.5"/><path d="M8 5v3.5L10 10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
          Période d'essai — <strong>{{ trialDaysLeft }} jour{{ trialDaysLeft > 1 ? 's' : '' }}</strong> restant{{ trialDaysLeft > 1 ? 's' : '' }}
        </div>

        <div v-if="loadingModules" class="loading-center" style="min-height:80px"><span class="spinner-sm"></span></div>

        <div v-else-if="modulesList.length" class="modules-grid-dash">
          <RouterLink
            v-for="mod in modulesList"
            :key="mod.code"
            :to="moduleRouteTarget(mod)"
            class="module-dash-card"
            :class="{
              'module-dash--active':   mod.tenant_active,
              'module-dash--inactive': !mod.tenant_active && mod.status !== 'coming_soon',
              'module-dash--soon':     mod.status === 'coming_soon',
            }"
          >
            <div class="module-dash-icon" :style="{ background: mod.color + '18', border: '1.5px solid ' + mod.color + '30' }">
              <span :style="{ color: mod.color }"><ModuleIcon :code="mod.code" /></span>
            </div>
            <div class="module-dash-info">
              <span class="module-dash-name">{{ mod.name }}</span>
              <span v-if="mod.tenant_active"         class="module-status-badge badge-success">Actif</span>
              <span v-else-if="mod.status === 'coming_soon'" class="module-status-badge badge-gray">Bientôt</span>
              <span v-else                           class="module-status-badge badge-warning">Inactif</span>
            </div>
          </RouterLink>
        </div>

        <div v-else class="empty-state" style="padding:1.5rem 0">
          <p>Aucun module configuré.</p>
        </div>
      </div>

      <!-- Quick actions -->
      <div class="card" style="margin-top: 1.5rem;">
        <div class="chart-card-header" style="margin-bottom: 1rem;">
          <h3>Actions rapides</h3>
        </div>
        <div class="quick-actions">
          <RouterLink to="/orders/new" class="quick-action">
            <span class="quick-action-icon">
              <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                <path d="M4 6h12M4 10h8M4 14h6" stroke="var(--brand-primary)" stroke-width="1.5" stroke-linecap="round"/>
                <circle cx="15" cy="14" r="3" stroke="var(--brand-primary)" stroke-width="1.5"/>
                <path d="M15 13v2M14 14h2" stroke="var(--brand-primary)" stroke-width="1.5" stroke-linecap="round"/>
              </svg>
            </span>
            <span>Nouvelle commande</span>
          </RouterLink>
          <RouterLink to="/catalog/products/create" class="quick-action">
            <span class="quick-action-icon">
              <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                <rect x="3" y="3" width="6" height="6" rx="1.5" stroke="var(--brand-primary)" stroke-width="1.5"/>
                <rect x="11" y="3" width="6" height="6" rx="1.5" stroke="var(--brand-primary)" stroke-width="1.5"/>
                <rect x="3" y="11" width="6" height="6" rx="1.5" stroke="var(--brand-primary)" stroke-width="1.5"/>
                <path d="M14 11v6M11 14h6" stroke="var(--brand-primary)" stroke-width="1.5" stroke-linecap="round"/>
              </svg>
            </span>
            <span>Ajouter un produit</span>
          </RouterLink>
          <RouterLink to="/inventory" class="quick-action">
            <span class="quick-action-icon">
              <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                <path d="M10 2L17 6V14L10 18L3 14V6L10 2Z" stroke="var(--brand-primary)" stroke-width="1.5" stroke-linejoin="round"/>
                <path d="M10 10L17 6M10 10L3 6M10 10V18" stroke="var(--brand-primary)" stroke-width="1.5"/>
              </svg>
            </span>
            <span>Gérer le stock</span>
          </RouterLink>
          <RouterLink to="/reports/sales" class="quick-action">
            <span class="quick-action-icon">
              <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                <rect x="3" y="12" width="3" height="5" rx="1" fill="var(--brand-primary)" opacity=".6"/>
                <rect x="8" y="8"  width="3" height="9" rx="1" fill="var(--brand-primary)" opacity=".7"/>
                <rect x="13" y="4" width="3" height="13" rx="1" fill="var(--brand-primary)"/>
              </svg>
            </span>
            <span>Rapport ventes</span>
          </RouterLink>
        </div>
      </div>

    </template>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, defineComponent, h } from 'vue'
import { RouterLink } from 'vue-router'
import { reportService, formatMoneyCompact, shortDate, type DashboardData } from '@/modules/reports/services/reportService'
import { authService } from '@/modules/auth/services/authService'
import { moduleRouteTarget } from '@/modules/dashboard/route'
import { useAuthStore } from '@/stores/auth'
import ModuleIcon from '@/shared/components/ModuleIcon.vue'
import type { ErpModule } from '@/modules/auth/types'

// ── Icons ─────────────────────────────────────────────────────────────────────

const IconRevenue = defineComponent({
  render: () => h('svg', { width: 20, height: 20, viewBox: '0 0 20 20', fill: 'none' }, [
    h('path', { d: 'M3 10h14M10 3c-3.866 0-7 3.134-7 7s3.134 7 7 7 7-3.134 7-7-3.134-7-7-7z', stroke: 'currentColor', 'stroke-width': '1.5', 'stroke-linecap': 'round' }),
    h('path', { d: 'M10 6v1.5m0 5V14', stroke: 'currentColor', 'stroke-width': '1.5', 'stroke-linecap': 'round' }),
  ]),
})

const IconOrders = defineComponent({
  render: () => h('svg', { width: 20, height: 20, viewBox: '0 0 20 20', fill: 'none' }, [
    h('path', { d: 'M5 4h10a1 1 0 011 1v11a1 1 0 01-1 1H5a1 1 0 01-1-1V5a1 1 0 011-1z', stroke: 'currentColor', 'stroke-width': '1.5' }),
    h('path', { d: 'M7 8h6M7 11h4', stroke: 'currentColor', 'stroke-width': '1.5', 'stroke-linecap': 'round' }),
  ]),
})

const IconProducts = defineComponent({
  render: () => h('svg', { width: 20, height: 20, viewBox: '0 0 20 20', fill: 'none' }, [
    h('path', { d: 'M10 2L17 6V14L10 18L3 14V6L10 2Z', stroke: 'currentColor', 'stroke-width': '1.5', 'stroke-linejoin': 'round' }),
    h('path', { d: 'M10 10L17 6M10 10L3 6M10 10V18', stroke: 'currentColor', 'stroke-width': '1.5' }),
  ]),
})

const IconAlerts = defineComponent({
  render: () => h('svg', { width: 20, height: 20, viewBox: '0 0 20 20', fill: 'none' }, [
    h('path', { d: 'M10 3L2 16h16L10 3z', stroke: 'currentColor', 'stroke-width': '1.5', 'stroke-linejoin': 'round' }),
    h('path', { d: 'M10 9v4M10 14.5v.5', stroke: 'currentColor', 'stroke-width': '1.5', 'stroke-linecap': 'round' }),
  ]),
})

// ── Inline bar chart ──────────────────────────────────────────────────────────

const RevenueBarChart = defineComponent({
  props: { points: { type: Array as () => { date: string; amount: number }[], required: true } },
  setup(props) {
    return () => {
      const max = Math.max(...props.points.map(p => p.amount), 1)
      const w   = 40
      const gap = 6
      const totalW = props.points.length * (w + gap) - gap

      if (!props.points.length) {
        return h('div', { class: 'chart-placeholder' }, [
          h('p', { style: 'color:var(--gray-400);font-size:.85rem' }, 'Aucune donnée de paiement pour les 7 derniers jours.')
        ])
      }

      return h('div', { class: 'bar-chart-wrap' }, [
        h('svg', {
          viewBox: `0 0 ${totalW} 80`,
          style: 'width:100%;overflow:visible',
          preserveAspectRatio: 'none',
        },
          props.points.map((p, i) => {
            const barH    = max > 0 ? Math.max((p.amount / max) * 68, p.amount > 0 ? 4 : 0) : 0
            const x       = i * (w + gap)
            const isToday = i === props.points.length - 1

            return h('g', { key: p.date }, [
              h('rect', {
                x,
                y: 70 - barH,
                width: w,
                height: barH,
                rx: 3,
                fill: isToday ? 'var(--brand-primary)' : 'var(--brand-primary)',
                opacity: isToday ? 1 : 0.45,
              }),
              h('text', {
                x: x + w / 2,
                y: 78,
                'text-anchor': 'middle',
                'font-size': '7',
                fill: 'var(--gray-400)',
              }, shortDate(p.date).replace(/\s/g, ' ')),
            ])
          })
        ),
      ])
    }
  },
})

// ── State ─────────────────────────────────────────────────────────────────────

const loading       = ref(true)
const dashboardData = ref<DashboardData | null>(null)
const loadingModules = ref(true)
const modulesList   = ref<ErpModule[]>([])
const auth          = useAuthStore()

// Subscription helpers
const subscriptionStatus = computed(() => auth.user?.subscription?.status ?? null)
const trialDaysLeft      = computed(() => {
  const end = auth.user?.subscription?.trial_ends_at
  if (!end) return null
  const diff = Math.ceil((new Date(end).getTime() - Date.now()) / 86_400_000)
  return diff > 0 ? diff : null
})

onMounted(async () => {
  // Load dashboard KPIs and modules in parallel
  await Promise.all([
    reportService.dashboard().then(d => { dashboardData.value = d }).catch(() => {}),
    authService.getModules().then(r => { modulesList.value = r.data }).catch(() => {}),
  ])
  loading.value       = false
  loadingModules.value = false
})

// ── KPI cards ─────────────────────────────────────────────────────────────────

const kpis = computed(() => {
  const d = dashboardData.value
  return [
    {
      icon:   IconRevenue,
      label:  'CA du jour',
      value:  d ? formatMoneyCompact(d.kpis.revenue_today) : '—',
      color:  'green',
      change: d?.kpis.revenue_today_change ?? null,
    },
    {
      icon:   IconOrders,
      label:  'Commandes',
      value:  d ? String(d.kpis.orders_today) : '—',
      color:  'blue',
      change: d?.kpis.orders_today_change ?? null,
    },
    {
      icon:   IconProducts,
      label:  'Produits actifs',
      value:  d ? String(d.kpis.active_products) : '—',
      color:  'purple',
      change: null,
    },
    {
      icon:   IconAlerts,
      label:  'Alertes stock',
      value:  d ? String(d.kpis.low_stock_alerts) : '—',
      color:  'orange',
      change: null,
    },
  ]
})

// ── Order table helpers ───────────────────────────────────────────────────────

function orderLabel(status: string): string {
  const m: Record<string, string> = {
    draft:     'Brouillon',
    confirmed: 'Confirmée',
    fulfilled: 'Livrée',
    cancelled: 'Annulée',
  }
  return m[status] ?? status
}

function orderBadge(status: string): string {
  const m: Record<string, string> = {
    draft:     'badge badge-gray',
    confirmed: 'badge badge-blue',
    fulfilled: 'badge badge-success',
    cancelled: 'badge badge-error',
  }
  return m[status] ?? 'badge badge-gray'
}
</script>

<style scoped>
.page-subtitle {
  color: var(--gray-500);
  font-size: var(--text-sm);
  margin-top: 0.25rem;
}

/* ── KPI grid ──────────────────────────────────────────────────────────── */
.kpi-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(210px, 1fr));
  gap: 1rem;
}

.kpi-card {
  background: white;
  border-radius: var(--radius-lg);
  border: 1px solid var(--gray-200);
  padding: 1.25rem 1.5rem;
  display: flex;
  align-items: flex-start;
  gap: 1rem;
  transition: box-shadow 0.15s, border-color 0.15s;
}
.kpi-card:hover {
  box-shadow: var(--shadow-md);
  border-color: var(--gray-300);
}

.kpi-icon-wrap {
  width: 40px;
  height: 40px;
  border-radius: var(--radius-md);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.kpi-icon--green  { background: var(--brand-primary-bg); color: var(--brand-primary-dark); }
.kpi-icon--blue   { background: var(--brand-secondary-light); color: var(--brand-secondary-dark); }
.kpi-icon--purple { background: #ede9fe; color: #7c3aed; }
.kpi-icon--orange { background: #fff7ed; color: #ea580c; }

.kpi-value {
  font-size: 1.6rem;
  font-weight: 700;
  color: var(--gray-900);
  line-height: 1;
}
.kpi-label {
  font-size: var(--text-xs);
  color: var(--gray-500);
  margin-top: 0.3rem;
  font-weight: 500;
}
.kpi-trend {
  display: flex;
  align-items: center;
  gap: 3px;
  font-size: var(--text-xs);
  margin-top: 0.4rem;
  font-weight: 500;
}
.trend-up   { color: var(--brand-primary-dark); }
.trend-down { color: var(--color-error); }

/* ── Charts row ─────────────────────────────────────────────────────────── */
.charts-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
  margin-top: 1.5rem;
}
@media (max-width: 768px) {
  .charts-row { grid-template-columns: 1fr; }
}

.chart-card {
  min-height: 220px;
  display: flex;
  flex-direction: column;
}
.chart-card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 0.5rem;
}
.chart-card-header h3 {
  font-size: var(--text-base);
  font-weight: 600;
  color: var(--gray-900);
  margin: 0;
}
.chart-card-link {
  font-size: var(--text-sm);
  color: var(--brand-secondary);
  text-decoration: none;
  font-weight: 500;
}
.chart-card-link:hover { text-decoration: underline; }

/* ── Bar chart ───────────────────────────────────────────────────────────── */
.bar-chart-wrap {
  flex: 1;
  padding: 0.75rem 0.25rem 0.5rem;
}

/* ── Chart placeholder ───────────────────────────────────────────────────── */
.chart-placeholder {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 0.75rem;
  padding: 2rem 1rem;
  background: var(--gray-50);
  border-radius: var(--radius-md);
  border: 1px dashed var(--gray-200);
  margin-top: 0.5rem;
}

/* ── Top products ────────────────────────────────────────────────────────── */
.top-products-list { display: flex; flex-direction: column; gap: 0.5rem; }
.top-product-row {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.5rem 0.75rem;
  border-radius: var(--radius-md);
  background: var(--gray-50);
}
.top-product-row:hover { background: var(--gray-100); }
.top-rank {
  width: 22px;
  height: 22px;
  border-radius: 50%;
  background: var(--brand-primary-bg);
  color: var(--brand-primary-dark);
  font-size: var(--text-xs);
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.top-product-info { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 2px; }
.top-product-name { font-size: var(--text-sm); font-weight: 600; color: var(--gray-800); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.top-product-sku  { font-size: var(--text-xs); }
.top-product-stats { display: flex; flex-direction: column; align-items: flex-end; gap: 2px; flex-shrink: 0; }
.top-product-qty  { font-size: var(--text-xs); color: var(--gray-500); }
.top-product-revenue { font-size: var(--text-sm); font-weight: 700; color: var(--brand-primary-dark); }

/* ── Links ───────────────────────────────────────────────────────────────── */
.link-blue { color: var(--brand-secondary); text-decoration: none; font-weight: 500; }
.link-blue:hover { text-decoration: underline; }
.text-muted { color: var(--gray-500); }

/* ── Quick actions ──────────────────────────────────────────────────────── */
.quick-actions { display: flex; gap: 0.75rem; flex-wrap: wrap; }
.quick-action {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  padding: 0.625rem 1rem;
  background: var(--brand-primary-bg);
  border: 1px solid var(--brand-primary-light);
  border-radius: var(--radius-md);
  text-decoration: none;
  color: var(--gray-700);
  font-size: var(--text-sm);
  font-weight: 500;
  transition: background 0.15s, border-color 0.15s, box-shadow 0.15s;
}
.quick-action:hover {
  background: var(--brand-primary-light);
  border-color: var(--brand-primary);
  box-shadow: 0 0 0 3px rgba(16,185,129,0.1);
}
.quick-action-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

/* ── Modules section ──────────────────────────────────────────────────────── */
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
  margin-bottom: 1rem;
}

.modules-grid-dash {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(170px, 1fr));
  gap: 0.75rem;
}

.module-dash-card {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.875rem 1rem;
  border-radius: var(--radius-md);
  border: 1px solid var(--gray-200);
  background: white;
  text-decoration: none;
  color: inherit;
  transition: box-shadow 0.15s, border-color 0.15s;
}
.module-dash--active:hover {
  box-shadow: var(--shadow-sm);
  border-color: var(--brand-primary-light);
}
.module-dash--inactive,
.module-dash--soon {
  opacity: 0.6;
  cursor: default;
  pointer-events: none;
}

.module-dash-icon {
  width: 36px;
  height: 36px;
  border-radius: var(--radius-md);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.module-dash-icon :deep(svg) {
  width: 18px;
  height: 18px;
}

.module-dash-info {
  display: flex;
  flex-direction: column;
  gap: 3px;
  min-width: 0;
}
.module-dash-name {
  font-size: var(--text-sm);
  font-weight: 600;
  color: var(--gray-800);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.module-status-badge {
  display: inline-block;
  font-size: 10px;
  font-weight: 600;
  padding: 1px 6px;
  border-radius: var(--radius-full);
  text-transform: uppercase;
  letter-spacing: 0.04em;
}
.badge-success {
  background: var(--brand-primary-bg);
  color: var(--brand-primary-dark);
}
.badge-warning {
  background: #fff7ed;
  color: #c2410c;
}
.badge-gray {
  background: var(--gray-100);
  color: var(--gray-500);
}
</style>
