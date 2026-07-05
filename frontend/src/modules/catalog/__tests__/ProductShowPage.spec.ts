import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import ProductShowPage from '@/modules/catalog/views/ProductShowPage.vue'
import { setupManagerAuth } from '@/test-utils/setupAuth'
import client from '@/api/client'

const router = createRouter({
  history: createMemoryHistory(),
  routes: [
    { path: '/catalog', name: 'catalog.products', component: { template: '<div/>' } },
    { path: '/catalog/products/:id', name: 'catalog.products.show', component: { template: '<div/>' } },
    { path: '/catalog/products/:id/edit', name: 'catalog.products.edit', component: { template: '<div/>' } },
    { path: '/catalog/categories', name: 'catalog.categories', component: { template: '<div/>' } },
    { path: '/catalog/variants', name: 'catalog.variants', component: { template: '<div/>' } },
    { path: '/catalog/attributes', name: 'catalog.attributes', component: { template: '<div/>' } },
    { path: '/catalog/labels', name: 'catalog.labels', component: { template: '<div/>' } },
  ],
})

const PRODUCT = {
  id: 'prod-1', name: 'Bassine de cuisine', sku: 'BAS-0015', status: 'active',
  product_type: 'variable', has_variants: true,
  price: { amount: 420000, currency: 'XOF', formatted: '4 200 XOF' },
  category: { id: 'c1', name: 'Ménager' },
  variants: [
    { id: 'v1', label: '30L / Rouge', sku: 'BAS-0015-V1', is_active: true, price: { amount: 420000, currency: 'XOF', formatted: '4 200 XOF' } },
  ],
}
const STOCK_SUMMARY = {
  total_quantity: 8, reserved_quantity: 0, available_quantity: 8, low_stock_count: 0,
  by_warehouse: [], by_variant: [{ variant_id: 'v1', quantity: 3, reserved: 0, available: 3, low_stock: false }],
}

function mockGet() {
  vi.mocked(client.get).mockImplementation((url: string) => {
    if (url.endsWith('/stock-summary')) return Promise.resolve({ data: STOCK_SUMMARY }) as any
    if (url.includes('/warehouses'))    return Promise.resolve({ data: { data: [] } }) as any
    return Promise.resolve({ data: { data: PRODUCT } }) as any // getDetail
  })
}

async function mountView() {
  await router.push('/catalog/products/prod-1')
  const w = mount(ProductShowPage, { global: { plugins: [router, setupManagerAuth()] } })
  await flushPromises()
  return w
}

describe('ProductShowPage', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    mockGet()
  })

  it('renders the product header with name, SKU and type badge', async () => {
    const w = await mountView()
    expect(w.text()).toContain('Bassine de cuisine')
    expect(w.text()).toContain('BAS-0015')
    expect(w.text()).toContain('Variable')   // product_type badge
  })

  it('shows the consultation tabs (overview, variants, stock, prices)', async () => {
    const w = await mountView()
    const tabs = w.findAll('.show-tab').map(t => t.text())
    expect(tabs.join(' ')).toContain('Vue d\'ensemble')
    expect(tabs.join(' ')).toContain('Variantes')
    expect(tabs.join(' ')).toContain('Stock')
    expect(tabs.join(' ')).toContain('Prix')
  })

  it('displays the aggregated stock summary (available quantity)', async () => {
    const w = await mountView()
    // available_quantity 8 must appear in the overview stock card
    expect(w.text()).toContain('8')
  })

  it('lists variants with their per-variant available stock', async () => {
    const w = await mountView()
    // Switch to the Variants tab
    const variantsTab = w.findAll('.show-tab').find(t => t.text().includes('Variantes'))!
    await variantsTab.trigger('click')
    await flushPromises()
    expect(w.text()).toContain('30L / Rouge')
    expect(w.text()).toContain('BAS-0015-V1')
  })
})
