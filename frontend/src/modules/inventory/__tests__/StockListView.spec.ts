import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import StockListView from '@/modules/inventory/views/StockListView.vue'
import { setupManagerAuth } from '@/test-utils/setupAuth'
import client from '@/api/client'

const router = createRouter({
  history: createMemoryHistory(),
  routes: [
    { path: '/inventory', name: 'inventory.stock', component: { template: '<div/>' } },
    { path: '/inventory/alerts', name: 'inventory.alerts', component: { template: '<div/>' } },
    { path: '/inventory/warehouses', name: 'inventory.warehouses', component: { template: '<div/>' } },
    { path: '/inventory/transfers', name: 'inventory.transfers', component: { template: '<div/>' } },
    { path: '/inventory/fiscal-periods', name: 'inventory.fiscal-periods', component: { template: '<div/>' } },
    { path: '/inventory/batch-delivery', name: 'inventory.batch-delivery', component: { template: '<div/>' } },
  ],
})

// Paginator at root (InventoryController returns response()->json($paginator))
const STOCK_PAGE = {
  data: [
    { id: 's1', product: { name: 'Riz parfumé 25kg', sku: 'RIZ-0001' }, available: 42, is_low_stock: false, low_stock_threshold: 5, quantity: 42 },
    { id: 's2', product: { name: 'Sardines x48', sku: 'SAR-0007' }, available: 2, is_low_stock: true, low_stock_threshold: 5, quantity: 2 },
  ],
  current_page: 1, last_page: 1, per_page: 50, total: 2,
}

function mockGet(page = STOCK_PAGE) {
  vi.mocked(client.get).mockImplementation((url: string) => {
    if (url.includes('/warehouses')) return Promise.resolve({ data: { data: [] } }) as any
    return Promise.resolve({ data: page }) as any // /api/inventory/stock
  })
}

async function mountView(page = STOCK_PAGE) {
  mockGet(page)
  const w = mount(StockListView, { global: { plugins: [router, setupManagerAuth()] } })
  await flushPromises()
  return w
}

describe('StockListView', () => {
  beforeEach(() => vi.clearAllMocks())

  it('renders stock rows with product name, SKU and available qty', async () => {
    const w = await mountView()
    expect(w.text()).toContain('Riz parfumé 25kg')
    expect(w.text()).toContain('RIZ-0001')
    expect(w.text()).toContain('42')
  })

  it('flags low-stock rows with a badge', async () => {
    const w = await mountView()
    expect(w.text()).toContain('Stock bas')
    // The low-stock row carries the warning class
    expect(w.find('.row-warning').exists()).toBe(true)
  })

  it('shows an empty state when there is no stock', async () => {
    const w = await mountView({ data: [], current_page: 1, last_page: 1, per_page: 50, total: 0 })
    expect(w.text().toLowerCase()).toContain('aucun stock')
  })
})
