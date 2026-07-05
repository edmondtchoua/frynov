<template>
  <div>

    <ReportsTabNav />

    <div class="page-header">
      <div>
        <h2>{{ $t('reports.insightsTitle') }}</h2>
        <p class="page-subtitle">{{ $t('reports.insightsSubtitle') }}</p>
      </div>
      <div class="header-actions">
        <select v-model.number="days" class="form-input" style="max-width: 160px" :aria-label="$t('reports.periodLabel')" data-test="period">
          <option :value="30">{{ $t('reports.days30') }}</option>
          <option :value="90">{{ $t('reports.days90') }}</option>
          <option :value="180">{{ $t('reports.days180') }}</option>
          <option :value="365">{{ $t('reports.days365') }}</option>
        </select>
      </div>
    </div>

    <div v-if="loading" class="loading-center" style="min-height:200px">
      <span class="spinner-sm"></span>
    </div>

    <div v-else-if="error" class="empty-state" data-test="error">{{ $t('reports.insightsError') }}</div>

    <template v-else>

      <!-- KPI cards (DSI / rotation / fill rate / stock mort) -->
      <div v-if="kpis" class="kpi-grid" data-test="kpis">
        <div class="kpi-card">
          <div class="kpi-icon-wrap kpi-icon--blue">
            <svg width="20" height="20" viewBox="0 0 20 20" fill="none"><circle cx="10" cy="10" r="7" stroke="currentColor" stroke-width="1.5"/><path d="M10 6v4l3 2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
          </div>
          <div class="kpi-body">
            <div class="kpi-value">{{ kpis.dsi ?? '—' }}</div>
            <div class="kpi-label">{{ $t('reports.kpiDsi') }}</div>
          </div>
        </div>
        <div class="kpi-card">
          <div class="kpi-icon-wrap kpi-icon--green">
            <svg width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M4 10a6 6 0 1 1 2 4.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M4 15v-4h4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </div>
          <div class="kpi-body">
            <div class="kpi-value">{{ kpis.rotation_rate ?? '—' }}</div>
            <div class="kpi-label">{{ $t('reports.kpiRotation') }}</div>
          </div>
        </div>
        <div class="kpi-card">
          <div class="kpi-icon-wrap kpi-icon--green">
            <svg width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M3 10l4 4 9-9" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </div>
          <div class="kpi-body">
            <div class="kpi-value">{{ kpis.fill_rate_pct !== null ? kpis.fill_rate_pct + ' %' : '—' }}</div>
            <div class="kpi-label">{{ $t('reports.kpiFillRate') }}</div>
          </div>
        </div>
        <div class="kpi-card">
          <div class="kpi-icon-wrap kpi-icon--orange">
            <svg width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M10 3L2 16h16L10 3z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><path d="M10 9v4M10 14.5v.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
          </div>
          <div class="kpi-body">
            <div class="kpi-value" :class="kpis.dead_stock_rate_pct > 20 ? 'kpi-warn' : ''">{{ kpis.dead_stock_rate_pct }} %</div>
            <div class="kpi-label">{{ $t('reports.kpiDeadStock') }}</div>
          </div>
        </div>
      </div>

      <div class="bottom-grid">

        <!-- ABC classification -->
        <div class="card" data-test="abc">
          <h3 class="section-title">{{ $t('reports.abcTitle') }}</h3>
          <div v-if="!abc || abc.items.length === 0" class="empty-state">{{ $t('reports.abcEmpty') }}</div>
          <template v-else>
            <div class="abc-summary">
              <div v-for="s in abc.summary" :key="s.class" class="abc-chip" :class="`abc-chip--${s.class.toLowerCase()}`">
                <span class="abc-class">{{ s.class }}</span>
                <span class="abc-detail">{{ $t('reports.abcChip', { count: s.product_count, share: s.revenue_share_pct }) }}</span>
              </div>
            </div>
            <div class="table-scroll">
              <table class="data-table">
                <thead>
                  <tr>
                    <th>{{ $t('reports.colProduct') }}</th>
                    <th class="num">{{ $t('reports.colRevenue') }}</th>
                    <th class="num">{{ $t('reports.colShare') }}</th>
                    <th class="num">{{ $t('reports.colCumulative') }}</th>
                    <th>{{ $t('reports.colClass') }}</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="item in abc.items.slice(0, 30)" :key="item.product_id">
                    <td>
                      <span class="abc-name">{{ item.product_name }}</span>
                      <code class="sku-code text-muted">{{ item.sku }}</code>
                    </td>
                    <td class="num">{{ formatMoneyCompact(item.revenue_cents) }}</td>
                    <td class="num">{{ item.revenue_share_pct }} %</td>
                    <td class="num">{{ item.cumulative_pct }} %</td>
                    <td><span class="abc-badge" :class="`abc-badge--${item.abc_class.toLowerCase()}`">{{ item.abc_class }}</span></td>
                  </tr>
                </tbody>
              </table>
            </div>
          </template>
        </div>

        <!-- Stock reconciliation -->
        <div class="card" data-test="reconciliation">
          <h3 class="section-title">
            {{ $t('reports.reconTitle') }}
            <span v-if="recon" class="text-muted" style="font-weight:400;font-size:var(--text-xs)">
              {{ $t('reports.reconTotal', { total: formatMoneyCompact(recon.total_erp_value) }) }}
            </span>
          </h3>
          <div v-if="!recon || recon.lines_by_category.length === 0" class="empty-state">{{ $t('reports.reconEmpty') }}</div>
          <div v-else class="table-scroll">
            <table class="data-table">
              <thead>
                <tr>
                  <th>{{ $t('reports.colCategory') }}</th>
                  <th class="num">{{ $t('reports.colSkus') }}</th>
                  <th class="num">{{ $t('reports.colQty') }}</th>
                  <th class="num">{{ $t('reports.colAvailable') }}</th>
                  <th class="num">{{ $t('reports.colValue') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="line in recon.lines_by_category" :key="line.category_name">
                  <td>{{ line.category_name }}</td>
                  <td class="num">{{ line.sku_count }}</td>
                  <td class="num">{{ line.total_qty }}</td>
                  <td class="num">{{ line.available_qty }}</td>
                  <td class="num">{{ formatMoneyCompact(line.erp_value_cents) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
import { ref, watch, onMounted } from 'vue'
import ReportsTabNav from '../components/ReportsTabNav.vue'
import {
  reportService, formatMoneyCompact,
  type AbcData, type InventoryKpisData, type ReconciliationData, type InsightsPeriod,
} from '../services/reportService'

const loading = ref(true)
const error = ref(false)
const days = ref<InsightsPeriod>(90)

const kpis = ref<InventoryKpisData | null>(null)
const abc = ref<AbcData | null>(null)
const recon = ref<ReconciliationData | null>(null)

async function load() {
  loading.value = true
  error.value = false
  try {
    // La réconciliation ne dépend pas de la période — chargée en parallèle des deux autres.
    const [k, a, r] = await Promise.all([
      reportService.inventoryKpis(days.value),
      reportService.abc(days.value),
      recon.value ? Promise.resolve(recon.value) : reportService.reconciliation(),
    ])
    kpis.value = k
    abc.value = a
    recon.value = r
  } catch {
    error.value = true
  } finally {
    loading.value = false
  }
}

watch(days, load)
onMounted(load)
</script>

<style scoped>
/* KPI grid — même gabarit que les autres onglets Rapports (styles scopés par vue). */
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
.kpi-value { font-size: 1.5rem; font-weight: 700; color: var(--gray-900); line-height: 1; }
.kpi-value.kpi-warn { color: #ea580c; }
.kpi-label { font-size: var(--text-xs); color: var(--gray-500); margin-top: 0.3rem; font-weight: 500; }

.bottom-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
  margin-top: 1.25rem;
}
@media (max-width: 1100px) { .bottom-grid { grid-template-columns: 1fr; } }

.section-title { font-size: var(--text-base); font-weight: 600; color: var(--gray-900); margin: 0 0 0.75rem; }
.sku-code { background: var(--gray-100); padding: 1px 5px; border-radius: 4px; font-size: var(--text-xs); font-family: monospace; }
.text-muted { color: var(--gray-500); }

.abc-summary { display: flex; gap: .6rem; margin-bottom: .9rem; flex-wrap: wrap; }
.abc-chip { display: flex; align-items: center; gap: .5rem; border-radius: 10px; padding: .4rem .7rem; font-size: .8rem; }
.abc-chip--a { background: #dcfce7; color: #166534; }
.abc-chip--b { background: #dbeafe; color: #1e40af; }
.abc-chip--c { background: #fef3c7; color: #92400e; }
.abc-class { font-weight: 800; font-size: .95rem; }
.abc-name { font-weight: 600; margin-right: .4rem; }

.abc-badge { display: inline-block; min-width: 22px; text-align: center; border-radius: 6px; font-weight: 700; font-size: .78rem; padding: .1rem .3rem; }
.abc-badge--a { background: #dcfce7; color: #166534; }
.abc-badge--b { background: #dbeafe; color: #1e40af; }
.abc-badge--c { background: #fef3c7; color: #92400e; }

.table-scroll { overflow-x: auto; }
.data-table { width: 100%; border-collapse: collapse; font-size: .85rem; }
.data-table th, .data-table td { padding: .45rem .6rem; text-align: left; border-bottom: 1px solid var(--gray-100, #f3f4f6); }
.data-table th { color: var(--gray-500); font-weight: 600; font-size: .75rem; text-transform: uppercase; letter-spacing: .02em; }
.data-table .num { text-align: right; }
</style>
