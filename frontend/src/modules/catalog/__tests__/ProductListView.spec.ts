import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import ProductListView from '@/modules/catalog/views/ProductListView.vue'
import { setupManagerAuth } from '@/test-utils/setupAuth'
import client from '@/api/client'

const router = createRouter({
  history: createMemoryHistory(),
  routes: [
    { path: '/catalog', name: 'catalog.products', component: { template: '<div/>' } },
    { path: '/catalog/products/create', name: 'catalog.products.create', component: { template: '<div/>' } },
    { path: '/catalog/products/:id', name: 'catalog.products.show', component: { template: '<div/>' } },
    { path: '/catalog/products/:id/edit', name: 'catalog.products.edit', component: { template: '<div/>' } },
    { path: '/catalog/categories', name: 'catalog.categories', component: { template: '<div/>' } },
    { path: '/catalog/variants', name: 'catalog.variants', component: { template: '<div/>' } },
    { path: '/catalog/attributes', name: 'catalog.attributes', component: { template: '<div/>' } },
    { path: '/catalog/labels', name: 'catalog.labels', component: { template: '<div/>' } },
  ],
})

const PRODUCTS = {
  data: [{
    id: 'p1', name: 'Bassine de cuisine', sku: 'BAS-0015', status: 'active',
    has_variants: true, variants_count: 10,
    price: { amount: 420000, currency: 'XOF', formatted: '4 200 XOF' },
    is_on_sale: false, category: { id: 'c1', name: 'Ménager' },
  }],
  meta: { current_page: 1, last_page: 1, total: 1, per_page: 20 },
}

function mockGet(products = PRODUCTS) {
  vi.mocked(client.get).mockImplementation((url: string) => {
    if (url.includes('/categories')) return Promise.resolve({ data: { data: [] } }) as any
    return Promise.resolve({ data: products }) as any // products list
  })
}

async function mountView(products = PRODUCTS) {
  mockGet(products)
  const w = mount(ProductListView, { global: { plugins: [router, setupManagerAuth()] } })
  await flushPromises()
  return w
}

describe('ProductListView', () => {
  beforeEach(() => vi.clearAllMocks())

  it('renders products with name, SKU and formatted price', async () => {
    const w = await mountView()
    expect(w.text()).toContain('Bassine de cuisine')
    expect(w.text()).toContain('BAS-0015')
    expect(w.text()).toContain('4 200 XOF')
  })

  it('makes the product name a link to the show page', async () => {
    const w = await mountView()
    const link = w.find('a.product-name-link')
    expect(link.exists()).toBe(true)
    expect(link.attributes('href')).toContain('/catalog/products/p1')
  })

  it('shows the variant count badge', async () => {
    const w = await mountView()
    expect(w.text()).toContain('10 variante')
  })

  it('shows an empty state when there are no products', async () => {
    const w = await mountView({ data: [], meta: { current_page: 1, last_page: 1, total: 0, per_page: 20 } })
    expect(w.text().toLowerCase()).toContain('aucun produit')
  })
})
