<template>
  <div>

    <div class="page-header">
      <h2>Tableau de bord</h2>
      <p class="page-subtitle">Vue d'ensemble de votre activité</p>
    </div>

    <!-- KPI cards -->
    <div class="kpi-grid">
      <div class="kpi-card" v-for="kpi in kpis" :key="kpi.label">
        <div class="kpi-icon-wrap" :class="`kpi-icon--${kpi.color}`">
          <component :is="kpi.icon" />
        </div>
        <div class="kpi-body">
          <div class="kpi-value">{{ kpi.value }}</div>
          <div class="kpi-label">{{ kpi.label }}</div>
          <div v-if="kpi.trend" class="kpi-trend" :class="kpi.trend > 0 ? 'trend-up' : 'trend-down'">
            <svg v-if="kpi.trend > 0" width="12" height="12" viewBox="0 0 12 12" fill="none">
              <path d="M6 2L10 6H7V10H5V6H2L6 2Z" fill="currentColor"/>
            </svg>
            <svg v-else width="12" height="12" viewBox="0 0 12 12" fill="none">
              <path d="M6 10L2 6H5V2H7V6H10L6 10Z" fill="currentColor"/>
            </svg>
            {{ Math.abs(kpi.trend) }}% vs hier
          </div>
        </div>
      </div>
    </div>

    <!-- Charts placeholder -->
    <div class="charts-row">
      <div class="card chart-card">
        <div class="chart-card-header">
          <h3>Chiffre d'affaires</h3>
          <span class="badge badge-gray">7 derniers jours</span>
        </div>
        <div class="chart-placeholder">
          <svg width="40" height="40" viewBox="0 0 40 40" fill="none" class="chart-placeholder-icon">
            <rect x="4" y="24" width="6" height="12" rx="2" fill="var(--brand-primary)" opacity="0.3"/>
            <rect x="13" y="16" width="6" height="20" rx="2" fill="var(--brand-primary)" opacity="0.5"/>
            <rect x="22" y="10" width="6" height="26" rx="2" fill="var(--brand-primary)" opacity="0.7"/>
            <rect x="31" y="4"  width="6" height="32" rx="2" fill="var(--brand-primary)"/>
          </svg>
          <p>Les graphiques seront disponibles après connexion au backend.</p>
        </div>
      </div>

      <div class="card chart-card">
        <div class="chart-card-header">
          <h3>Commandes récentes</h3>
          <RouterLink to="/orders" class="chart-card-link">Voir tout →</RouterLink>
        </div>
        <div class="chart-placeholder">
          <svg width="40" height="40" viewBox="0 0 40 40" fill="none" class="chart-placeholder-icon">
            <rect x="4" y="8" width="32" height="4" rx="2" fill="var(--brand-secondary)" opacity="0.3"/>
            <rect x="4" y="17" width="24" height="4" rx="2" fill="var(--brand-secondary)" opacity="0.5"/>
            <rect x="4" y="26" width="28" height="4" rx="2" fill="var(--brand-secondary)" opacity="0.7"/>
          </svg>
          <p>Les commandes seront visibles ici en temps réel.</p>
        </div>
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
        <RouterLink to="/customers" class="quick-action">
          <span class="quick-action-icon">
            <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
              <circle cx="10" cy="7" r="3" stroke="var(--brand-primary)" stroke-width="1.5"/>
              <path d="M4 17c0-3.314 2.686-6 6-6s6 2.686 6 6" stroke="var(--brand-primary)" stroke-width="1.5" stroke-linecap="round"/>
            </svg>
          </span>
          <span>Clients</span>
        </RouterLink>
      </div>
    </div>

  </div>
</template>

<script setup lang="ts">
import { defineComponent, h } from 'vue'
import { RouterLink } from 'vue-router'

// SVG icon components inline
const IconRevenue = defineComponent({
  render: () => h('svg', { width: 20, height: 20, viewBox: '0 0 20 20', fill: 'none' }, [
    h('path', { d: 'M10 2v1m0 14v1M4.22 4.22l.71.71m10.14 10.14.71.71M2 10h1m14 0h1M4.93 15.07l.71-.71M14.36 5.64l.71-.71', stroke: 'currentColor', 'stroke-width': '1.5', 'stroke-linecap': 'round' }),
    h('circle', { cx: 10, cy: 10, r: 3, stroke: 'currentColor', 'stroke-width': '1.5' }),
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

const kpis = [
  { icon: IconRevenue, label: 'CA du jour',       value: '—', color: 'green',  trend: null },
  { icon: IconOrders,  label: 'Commandes',         value: '—', color: 'blue',   trend: null },
  { icon: IconProducts,label: 'Produits actifs',   value: '—', color: 'purple', trend: null },
  { icon: IconAlerts,  label: 'Alertes stock',     value: '—', color: 'orange', trend: null },
]
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
  .charts-row {
    grid-template-columns: 1fr;
  }
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
}
.chart-placeholder-icon { opacity: 0.8; }
.chart-placeholder p {
  font-size: var(--text-sm);
  color: var(--gray-400);
  text-align: center;
  margin: 0;
}

/* ── Quick actions ──────────────────────────────────────────────────────── */
.quick-actions {
  display: flex;
  gap: 0.75rem;
  flex-wrap: wrap;
}

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
</style>
