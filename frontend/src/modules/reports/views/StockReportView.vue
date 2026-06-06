<template>
  <div>

    <ReportsTabNav />

    <div class="page-header">
      <div>
        <h2>Rapport de stock</h2>
        <p class="page-subtitle">Valeur du stock, ruptures et produits en alerte</p>
      </div>
      <div class="header-actions">
        <RouterLink to="/inventory/alerts" class="btn btn-secondary btn-sm">
          Voir les alertes →
        </RouterLink>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="loading-center" style="min-height:200px">
      <span class="spinner-sm"></span>
    </div>

    <template v-else-if="data">

      <!-- KPI cards -->
      <div class="kpi-grid">
        <div class="kpi-card">
          <div class="kpi-icon-wrap kpi-icon--green">
            <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
              <path d="M10 2L17 6V14L10 18L3 14V6L10 2Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
              <path d="M10 10L17 6M10 10L3 6M10 10V18" stroke="currentColor" stroke-width="1.5"/>
            </svg>
          </div>
          <div class="kpi-body">
            <div class="kpi-value">{{ formatMoneyCompact(data.stock_value) }}</div>
            <div class="kpi-label">Valeur du stock (coût)</div>
          </div>
        </div>

        <div class="kpi-card">
          <div class="kpi-icon-wrap kpi-icon--blue">
            <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
              <rect x="3" y="3" width="6" height="6" rx="1.5" stroke="currentColor" stroke-width="1.5"/>
              <rect x="11" y="3" width="6" height="6" rx="1.5" stroke="currentColor" stroke-width="1.5"/>
              <rect x="3" y="11" width="6" height="6" rx="1.5" stroke="currentColor" stroke-width="1.5"/>
              <rect x="11" y="11" width="6" height="6" rx="1.5" stroke="currentColor" stroke-width="1.5"/>
            </svg>
          </div>
          <div class="kpi-body">
            <div class="kpi-value">{{ data.total_skus }}</div>
            <div class="kpi-label">Références suivies</div>
          </div>
        </div>

        <div class="kpi-card">
          <div class="kpi-icon-wrap kpi-icon--orange">
            <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
              <path d="M10 3L2 16h16L10 3z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
              <path d="M10 9v4M10 14.5v.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
            </svg>
          </div>
          <div class="kpi-body">
            <div class="kpi-value" :class="data.low_stock_count > 0 ? 'kpi-warn' : ''">{{ data.low_stock_count }}</div>
            <div class="kpi-label">En alerte stock bas</div>
          </div>
        </div>

        <div class="kpi-card">
          <div class="kpi-icon-wrap kpi-icon--error">
            <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
              <circle cx="10" cy="10" r="7" stroke="currentColor" stroke-width="1.5"/>
              <path d="M10 7v4M10 13.5v.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
            </svg>
          </div>
          <div class="kpi-body">
            <div class="kpi-value" :class="data.out_of_stock > 0 ? 'kpi-error' : ''">{{ data.out_of_stock }}</div>
            <div class="kpi-label">En rupture de stock</div>
          </div>
        </div>
      </div>

      <!-- Bottom grid -->
      <div class="bottom-grid">

        <!-- Low stock items -->
        <div class="card">
          <h3 class="section-title">
            Produits en alerte
            <span v-if="data.low_stock_count > 10" class="text-muted" style="font-weight:400;font-size:var(--text-xs)"> (top 10)</span>
          </h3>
          <div v-if="!data.low_stock_items.length" class="empty-state">Aucun produit en alerte stock.</div>
          <div v-else class="alert-list">
            <div v-for="item in data.low_stock_items" :key="item.id" class="alert-item">
              <div class="alert-item-info">
                <span class="alert-product-name">{{ item.product?.name ?? '—' }}</span>
                <code class="sku-code text-muted">{{ item.product?.sku ?? '' }}</code>
              </div>
              <div class="alert-stock-bar">
                <div
                  class="alert-stock-fill"
                  :style="{ width: `${item.low_stock_threshold > 0 ? Math.min((item.quantity / item.low_stock_threshold) * 100, 100) : 0}%` }"
                  :class="item.quantity <= 0 ? 'fill-error' : item.quantity <= item.low_stock_threshold * 0.5 ? 'fill-warn-dark' : 'fill-warn'"
                ></div>
              </div>
              <div class="alert-qty">
                <span :class="item.quantity <= 0 ? 'qty-error' : 'qty-warn'">{{ item.quantity }}</span>
                <span class="text-muted" style="font-size:.7rem"> / {{ item.low_stock_threshold }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Recent movements by type -->
        <div class="card">
          <h3 class="section-title">Mouvements — 30 derniers jours</h3>
          <div v-if="!data.recent_movements.length" class="empty-state">Aucun mouvement enregistré.</div>
          <div v-else class="movements-list">
            <div v-for="m in data.recent_movements" :key="m.type" class="movement-row">
              <span class="movement-icon" :class="`movement-icon--${m.type}`">
                <svg v-if="m.type === 'in'" width="14" height="14" viewBox="0 0 14 14" fill="none">
                  <path d="M7 2v10M2 7l5-5 5 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <svg v-else-if="m.type === 'out'" width="14" height="14" viewBox="0 0 14 14" fill="none">
                  <path d="M7 12V2M2 7l5 5 5-5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <svg v-else width="14" height="14" viewBox="0 0 14 14" fill="none">
                  <path d="M2 7h10M7 2l5 5-5 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
              </span>
              <div class="movement-info">
                <span class="movement-type">{{ movementLabel(m.type) }}</span>
                <span class="text-muted">{{ m.count }} opération{{ m.count > 1 ? 's' : '' }}</span>
              </div>
              <div class="movement-bar-wrap">
                <div
                  class="movement-bar"
                  :class="`movement-bar--${m.type}`"
                  :style="{ width: `${maxMovementQty > 0 ? (m.total_qty / maxMovementQty) * 100 : 0}%` }"
                ></div>
              </div>
              <span class="movement-qty">{{ m.total_qty }} unités</span>
            </div>
          </div>
        </div>

      </div>

    </template>

    <div v-else class="alert alert-error" style="margin-top:1.5rem">
      Erreur de chargement des données.
    </div>

  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import ReportsTabNav from '../components/ReportsTabNav.vue'
import { reportService, formatMoneyCompact, type StockData } from '../services/reportService'

const loading = ref(true)
const data    = ref<StockData | null>(null)

onMounted(async () => {
  try {
    data.value = await reportService.stock()
  } catch {
    data.value = null
  } finally {
    loading.value = false
  }
})

const maxMovementQty = computed(() => {
  if (!data.value?.recent_movements?.length) return 1
  return Math.max(...data.value.recent_movements.map(m => Number(m.total_qty)), 1)
})

function movementLabel(type: string): string {
  const m: Record<string, string> = {
    in:         'Entrées',
    out:        'Sorties',
    adjustment: 'Ajustements',
    return:     'Retours',
  }
  return m[type] ?? type
}
</script>

<style scoped>
.page-subtitle { color: var(--gray-500); font-size: var(--text-sm); margin-top: 0.25rem; }
.header-actions { display: flex; gap: 0.5rem; }

/* KPI grid */
.kpi-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
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
}
.kpi-icon-wrap {
  width: 40px; height: 40px;
  border-radius: var(--radius-md);
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}
.kpi-icon--green  { background: var(--brand-primary-bg); color: var(--brand-primary-dark); }
.kpi-icon--blue   { background: var(--brand-secondary-light); color: var(--brand-secondary-dark); }
.kpi-icon--orange { background: #fff7ed; color: #ea580c; }
.kpi-icon--error  { background: #fef2f2; color: var(--color-error); }
.kpi-value { font-size: 1.5rem; font-weight: 700; color: var(--gray-900); line-height: 1; }
.kpi-value.kpi-warn  { color: #ea580c; }
.kpi-value.kpi-error { color: var(--color-error); }
.kpi-label { font-size: var(--text-xs); color: var(--gray-500); margin-top: 0.3rem; font-weight: 500; }

/* Bottom grid */
.bottom-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
  margin-top: 1.25rem;
}
@media (max-width: 900px) { .bottom-grid { grid-template-columns: 1fr; } }

.section-title { font-size: var(--text-base); font-weight: 600; color: var(--gray-900); margin: 0 0 0.75rem; }
.sku-code { background: var(--gray-100); padding: 1px 5px; border-radius: 4px; font-size: var(--text-xs); font-family: monospace; }
.text-muted { color: var(--gray-500); }

/* Alert list */
.alert-list { display: flex; flex-direction: column; gap: 0.6rem; }
.alert-item {
  display: grid;
  grid-template-columns: 1fr 80px 60px;
  align-items: center;
  gap: 0.5rem;
  padding: 0.5rem 0.75rem;
  border-radius: var(--radius-md);
  background: var(--gray-50);
}
.alert-item:hover { background: var(--gray-100); }
.alert-item-info { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
.alert-product-name { font-size: var(--text-sm); font-weight: 500; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.alert-stock-bar { height: 6px; background: var(--gray-200); border-radius: 99px; overflow: hidden; }
.alert-stock-fill { height: 100%; border-radius: 99px; transition: width 0.3s; }
.fill-warn      { background: var(--color-warning); }
.fill-warn-dark { background: #f97316; }
.fill-error     { background: var(--color-error); width: 0 !important; }
.alert-qty { text-align: right; font-size: var(--text-sm); font-weight: 700; }
.qty-warn  { color: #ea580c; }
.qty-error { color: var(--color-error); }

/* Movements */
.movements-list { display: flex; flex-direction: column; gap: 0.75rem; }
.movement-row {
  display: grid;
  grid-template-columns: 28px 130px 1fr auto;
  align-items: center;
  gap: 0.6rem;
}
.movement-icon {
  width: 28px; height: 28px;
  border-radius: var(--radius-md);
  display: flex; align-items: center; justify-content: center;
}
.movement-icon--in         { background: #d1fae5; color: var(--brand-primary-dark); }
.movement-icon--out        { background: #fee2e2; color: var(--color-error); }
.movement-icon--adjustment { background: #dbeafe; color: var(--brand-secondary); }
.movement-icon--return     { background: #ede9fe; color: #7c3aed; }
.movement-info { display: flex; flex-direction: column; gap: 1px; }
.movement-type { font-size: var(--text-sm); font-weight: 500; }
.movement-bar-wrap { height: 8px; background: var(--gray-100); border-radius: 99px; overflow: hidden; }
.movement-bar { height: 100%; border-radius: 99px; transition: width 0.4s; }
.movement-bar--in         { background: var(--brand-primary); }
.movement-bar--out        { background: var(--color-error); }
.movement-bar--adjustment { background: var(--brand-secondary); }
.movement-bar--return     { background: #7c3aed; }
.movement-qty { font-size: var(--text-sm); font-weight: 600; color: var(--gray-700); white-space: nowrap; }
</style>
