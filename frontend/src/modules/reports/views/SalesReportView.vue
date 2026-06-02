<template>
  <div>

    <ReportsTabNav />

    <div class="page-header">
      <div>
        <h2>Rapport des ventes</h2>
        <p class="page-subtitle">Chiffre d'affaires, top produits et méthodes de paiement</p>
      </div>
      <div class="header-actions">
        <button
          v-for="p in PERIODS"
          :key="p.value"
          class="btn btn-sm"
          :class="period === p.value ? 'btn-primary' : 'btn-secondary'"
          @click="changePeriod(p.value)"
        >
          {{ p.label }}
        </button>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="loading-center" style="min-height:200px">
      <span class="spinner-sm"></span>
    </div>

    <template v-else-if="data">

      <!-- Summary KPIs -->
      <div class="kpi-grid">
        <div class="kpi-card">
          <div class="kpi-icon-wrap kpi-icon--green">
            <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
              <path d="M3 10h14M10 3c-3.866 0-7 3.134-7 7s3.134 7 7 7 7-3.134 7-7-3.134-7-7-7z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
              <path d="M10 6v1.5m0 5V14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
            </svg>
          </div>
          <div class="kpi-body">
            <div class="kpi-value">{{ formatMoneyCompact(data.total_revenue) }}</div>
            <div class="kpi-label">CA total — {{ periodLabel }}</div>
          </div>
        </div>

        <div class="kpi-card">
          <div class="kpi-icon-wrap kpi-icon--blue">
            <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
              <path d="M5 4h10a1 1 0 011 1v11a1 1 0 01-1 1H5a1 1 0 01-1-1V5a1 1 0 011-1z" stroke="currentColor" stroke-width="1.5"/>
              <path d="M7 8h6M7 11h4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
            </svg>
          </div>
          <div class="kpi-body">
            <div class="kpi-value">{{ data.total_orders }}</div>
            <div class="kpi-label">Paiements enregistrés</div>
          </div>
        </div>

        <div class="kpi-card">
          <div class="kpi-icon-wrap kpi-icon--purple">
            <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
              <path d="M10 3v14M3 10h14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
            </svg>
          </div>
          <div class="kpi-body">
            <div class="kpi-value">{{ avgOrder }}</div>
            <div class="kpi-label">Panier moyen</div>
          </div>
        </div>

        <div class="kpi-card">
          <div class="kpi-icon-wrap kpi-icon--orange">
            <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
              <rect x="3" y="12" width="3" height="5" rx="1" fill="currentColor" opacity=".6"/>
              <rect x="8" y="8"  width="3" height="9" rx="1" fill="currentColor" opacity=".7"/>
              <rect x="13" y="4" width="3" height="13" rx="1" fill="currentColor"/>
            </svg>
          </div>
          <div class="kpi-body">
            <div class="kpi-value">{{ topMethodLabel }}</div>
            <div class="kpi-label">Méthode principale</div>
          </div>
        </div>
      </div>

      <!-- Revenue chart -->
      <div class="card" style="margin-top:1.25rem">
        <div class="chart-card-header">
          <h3>Évolution du CA</h3>
          <span class="badge badge-gray">{{ periodLabel }}</span>
        </div>
        <RevenueChart :points="data.revenue_chart" />
      </div>

      <!-- Bottom grid: top products + methods -->
      <div class="bottom-grid">

        <!-- Top products -->
        <div class="card">
          <h3 class="section-title">Top produits</h3>
          <div v-if="!data.top_products.length" class="empty-state">Aucune vente sur la période.</div>
          <table v-else class="data-table">
            <thead>
              <tr>
                <th>#</th>
                <th>Produit</th>
                <th>SKU</th>
                <th style="text-align:right">Qté</th>
                <th style="text-align:right">CA</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(p, i) in data.top_products" :key="p.product_id ?? i">
                <td style="color:var(--gray-400);font-weight:700;width:28px">{{ i + 1 }}</td>
                <td style="font-weight:500">{{ p.product_name }}</td>
                <td><code class="sku-code">{{ p.sku }}</code></td>
                <td style="text-align:right;font-variant-numeric:tabular-nums">{{ p.total_qty }}</td>
                <td style="text-align:right;font-weight:600;color:var(--brand-primary-dark)">{{ formatMoneyCompact(p.total_revenue) }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Payment methods -->
        <div class="card">
          <h3 class="section-title">Répartition par moyen de paiement</h3>
          <div v-if="!data.by_method.length" class="empty-state">Aucun paiement sur la période.</div>
          <div v-else class="methods-list">
            <div v-for="m in data.by_method" :key="m.method" class="method-row">
              <div class="method-info">
                <span class="method-name">{{ methodLabel(m.method) }}</span>
                <span class="method-count text-muted">{{ m.count }} paiement{{ m.count > 1 ? 's' : '' }}</span>
              </div>
              <div class="method-bar-wrap">
                <div
                  class="method-bar"
                  :style="{ width: `${maxMethodAmount > 0 ? (m.amount / maxMethodAmount) * 100 : 0}%` }"
                ></div>
              </div>
              <span class="method-amount">{{ formatMoneyCompact(m.amount) }}</span>
            </div>
          </div>
        </div>

      </div>

    </template>

    <!-- Error -->
    <div v-else class="alert alert-error" style="margin-top:1.5rem">
      Erreur de chargement des données.
    </div>

  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, defineComponent, h, watch } from 'vue'
import ReportsTabNav from '../components/ReportsTabNav.vue'
import { reportService, formatMoneyCompact, shortDate, type SalesData, type SalesPeriod } from '../services/reportService'

// ── Period selector ───────────────────────────────────────────────────────────

const PERIODS: { value: SalesPeriod; label: string }[] = [
  { value: '7d',  label: '7J' },
  { value: '30d', label: '30J' },
  { value: '90d', label: '90J' },
  { value: '1y',  label: '1 an' },
]

const period  = ref<SalesPeriod>('30d')
const loading = ref(true)
const data    = ref<SalesData | null>(null)

async function load() {
  loading.value = true
  try {
    data.value = await reportService.sales(period.value)
  } catch {
    data.value = null
  } finally {
    loading.value = false
  }
}

onMounted(load)
watch(period, load)

function changePeriod(p: SalesPeriod) { period.value = p }

// ── Computed ──────────────────────────────────────────────────────────────────

const periodLabel = computed(() => {
  const m: Record<SalesPeriod, string> = { '7d': '7 derniers jours', '30d': '30 derniers jours', '90d': '90 derniers jours', '1y': 'Dernière année' }
  return m[period.value]
})

const avgOrder = computed(() => {
  if (!data.value || data.value.total_orders === 0) return '—'
  return formatMoneyCompact(Math.round(data.value.total_revenue / data.value.total_orders))
})

const topMethodLabel = computed(() => {
  if (!data.value?.by_method?.length) return '—'
  return methodLabel(data.value.by_method[0].method)
})

const maxMethodAmount = computed(() => {
  if (!data.value?.by_method?.length) return 1
  return Math.max(...data.value.by_method.map(m => Number(m.amount)), 1)
})

function methodLabel(m: string): string {
  const map: Record<string, string> = {
    cash:         'Espèces',
    mobile_money: 'Mobile Money',
    card:         'Carte bancaire',
    transfer:     'Virement',
    cheque:       'Chèque',
  }
  return map[m] ?? m
}

// ── Revenue chart (SVG bars) ──────────────────────────────────────────────────

const RevenueChart = defineComponent({
  props: { points: { type: Array as () => { date: string; amount: number }[], required: true } },
  setup(props) {
    return () => {
      const max = Math.max(...props.points.map(p => p.amount), 1)

      if (!props.points.length) {
        return h('div', { class: 'chart-empty' }, 'Aucune donnée.')
      }

      const barW    = period.value === '1y' ? 6 : (period.value === '90d' ? 8 : (period.value === '30d' ? 14 : 28))
      const gap     = period.value === '1y' ? 2 : (period.value === '90d' ? 2 : 4)
      const totalW  = props.points.length * (barW + gap) - gap
      const H       = 100

      return h('div', { class: 'revenue-chart-wrap' }, [
        h('svg', {
          viewBox: `0 0 ${totalW} ${H + 16}`,
          style: 'width:100%;overflow:visible',
          preserveAspectRatio: 'none',
        },
          props.points.map((p, i) => {
            const barH    = max > 0 ? Math.max((p.amount / max) * H, p.amount > 0 ? 3 : 0) : 0
            const x       = i * (barW + gap)
            const isToday = i === props.points.length - 1

            const els = [
              h('rect', {
                x,
                y: H - barH,
                width: barW,
                height: barH,
                rx: 2,
                fill: isToday ? 'var(--brand-primary)' : 'var(--brand-primary)',
                opacity: isToday ? 1 : 0.5,
              }),
            ]

            // Show date labels only for reasonable bar sizes
            if (barW >= 14 && i % Math.ceil(props.points.length / 10) === 0) {
              els.push(h('text', {
                x: x + barW / 2,
                y: H + 12,
                'text-anchor': 'middle',
                'font-size': '6',
                fill: 'var(--gray-400)',
              }, shortDate(p.date)))
            }

            return h('g', { key: p.date }, els)
          })
        ),
      ])
    }
  },
})
</script>

<style scoped>
.page-subtitle { color: var(--gray-500); font-size: var(--text-sm); margin-top: 0.25rem; }

.header-actions {
  display: flex;
  gap: 0.4rem;
}

/* ── KPI grid ── */
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
.kpi-icon--purple { background: #ede9fe; color: #7c3aed; }
.kpi-icon--orange { background: #fff7ed; color: #ea580c; }
.kpi-value  { font-size: 1.5rem; font-weight: 700; color: var(--gray-900); line-height: 1; }
.kpi-label  { font-size: var(--text-xs); color: var(--gray-500); margin-top: 0.3rem; font-weight: 500; }

/* ── Chart ── */
.chart-card-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.75rem; }
.chart-card-header h3 { font-size: var(--text-base); font-weight: 600; color: var(--gray-900); margin: 0; }
.revenue-chart-wrap { padding: 0.75rem 0 0.25rem; }
.chart-empty { color: var(--gray-400); font-size: var(--text-sm); padding: 2rem; text-align: center; }

/* ── Bottom grid ── */
.bottom-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
  margin-top: 1.25rem;
}
@media (max-width: 900px) { .bottom-grid { grid-template-columns: 1fr; } }

.section-title { font-size: var(--text-base); font-weight: 600; color: var(--gray-900); margin: 0 0 0.75rem; }

.sku-code { background: var(--gray-100); color: var(--gray-600); padding: 1px 5px; border-radius: 4px; font-size: var(--text-xs); font-family: monospace; }

/* ── Methods ── */
.methods-list { display: flex; flex-direction: column; gap: 0.75rem; }
.method-row {
  display: grid;
  grid-template-columns: 140px 1fr auto;
  align-items: center;
  gap: 0.75rem;
}
.method-info { display: flex; flex-direction: column; gap: 2px; }
.method-name { font-size: var(--text-sm); font-weight: 500; color: var(--gray-800); }
.method-count { font-size: var(--text-xs); }
.method-bar-wrap { height: 8px; background: var(--gray-100); border-radius: 99px; overflow: hidden; }
.method-bar { height: 100%; background: var(--brand-primary); border-radius: 99px; transition: width 0.4s ease; }
.method-amount { font-size: var(--text-sm); font-weight: 700; color: var(--brand-primary-dark); white-space: nowrap; }
.text-muted { color: var(--gray-500); }
</style>
