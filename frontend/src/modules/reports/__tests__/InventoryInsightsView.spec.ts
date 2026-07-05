import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createWebHistory } from 'vue-router'
import InventoryInsightsView from '@/modules/reports/views/InventoryInsightsView.vue'
import { setupManagerAuth } from '@/test-utils/setupAuth'

const { reportService } = vi.hoisted(() => ({
  reportService: {
    abc: vi.fn(), inventoryKpis: vi.fn(), reconciliation: vi.fn(),
    dashboard: vi.fn(), sales: vi.fn(), stock: vi.fn(), specialProducts: vi.fn(),
  },
}))
vi.mock('@/modules/reports/services/reportService', async (importOriginal) => {
  const orig = await importOriginal<any>()
  return { ...orig, reportService }
})

const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/reports/sales', component: { template: '<div/>' } },
    { path: '/reports/stock', component: { template: '<div/>' } },
    { path: '/reports/insights', component: { template: '<div/>' } },
    { path: '/inventory/alerts', component: { template: '<div/>' } },
  ],
})

const KPIS = {
  period_days: 90, dsi: 42.5, rotation_rate: 8.6, fill_rate_pct: 96.4,
  dead_stock_rate_pct: 3.1, cogs_cents: 1000000, total_stock_value: 450000,
  total_orders: 120, fulfilled_orders: 116,
}
const ABC = {
  period_days: 90, total_revenue: 5000000,
  summary: [{ class: 'A', product_count: 4, revenue_cents: 4000000, revenue_share_pct: 80 }],
  items: [{
    product_id: 'p1', product_name: 'Riz parfumé', sku: 'RIZ-1', revenue_cents: 2500000,
    revenue_share_pct: 50, cumulative_pct: 50, total_qty_sold: 100, abc_class: 'A',
  }],
}
const RECON = {
  generated_at: '2026-07-05T10:00:00Z', total_erp_value: 450000,
  lines_by_category: [{ category_name: 'Épicerie', sku_count: 12, total_qty: 340, available_qty: 300, erp_value_cents: 450000, avg_cmup_cents: 1300 }],
  movements_summary: [],
}

async function mountView() {
  await router.push('/reports/insights')
  const w = mount(InventoryInsightsView, { global: { plugins: [router, setupManagerAuth()] } })
  await flushPromises()
  return w
}

describe('InventoryInsightsView', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    reportService.inventoryKpis.mockResolvedValue(KPIS)
    reportService.abc.mockResolvedValue(ABC)
    reportService.reconciliation.mockResolvedValue(RECON)
  })

  it('renders the KPI cards, ABC table and reconciliation from the three endpoints', async () => {
    const w = await mountView()

    expect(reportService.inventoryKpis).toHaveBeenCalledWith(90)
    expect(reportService.abc).toHaveBeenCalledWith(90)
    expect(reportService.reconciliation).toHaveBeenCalledTimes(1)

    expect(w.find('[data-test="kpis"]').text()).toContain('42.5')      // DSI
    expect(w.find('[data-test="abc"]').text()).toContain('RIZ-1')
    expect(w.find('[data-test="reconciliation"]').text()).toContain('Épicerie')
  })

  it('reloads KPIs and ABC when the period changes (reconciliation cached)', async () => {
    const w = await mountView()
    await w.find('[data-test="period"]').setValue('30')
    await flushPromises()

    expect(reportService.inventoryKpis).toHaveBeenLastCalledWith(30)
    expect(reportService.abc).toHaveBeenLastCalledWith(30)
    expect(reportService.reconciliation).toHaveBeenCalledTimes(1)      // pas rechargée
  })

  it('shows the error state when an endpoint fails', async () => {
    reportService.inventoryKpis.mockRejectedValue(new Error('boom'))
    const w = await mountView()

    expect(w.find('[data-test="error"]').exists()).toBe(true)
  })
})
