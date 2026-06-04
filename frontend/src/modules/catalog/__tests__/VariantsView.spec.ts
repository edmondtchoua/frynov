import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import VariantsView from '@/modules/catalog/views/VariantsView.vue'
import { setupManagerAuth } from '@/test-utils/setupAuth'
import client from '@/api/client'

const router = createRouter({
  history: createMemoryHistory(),
  routes: [
    { path: '/catalog', name: 'catalog.products', component: { template: '<div/>' } },
    { path: '/catalog/products/:id', name: 'catalog.products.show', component: { template: '<div/>' } },
    { path: '/catalog/categories', name: 'catalog.categories', component: { template: '<div/>' } },
    { path: '/catalog/variants', name: 'catalog.variants', component: { template: '<div/>' } },
    { path: '/catalog/attributes', name: 'catalog.attributes', component: { template: '<div/>' } },
    { path: '/catalog/labels', name: 'catalog.labels', component: { template: '<div/>' } },
  ],
})

// Laravel paginator shape (data at root, no `meta` wrapper)
const PAGINATOR = {
  data: [{
    id: 'v1', product_id: 'p1', sku: 'BAS-0015-V1', label: '30L / Rouge',
    price_amount: 420000, price_currency: 'XOF',   // 4 200 XOF in centimes
    stock_available: 3, stock_qty: 5,
    attribute_chips: [{ name: 'Taille', label: '30L' }, { name: 'Couleur', label: 'Rouge' }],
    product: { id: 'p1', name: 'Bassine', sku: 'BAS-0015', category: { id: 'c1', name: 'Ménager' } },
  }],
  total: 1, current_page: 1, last_page: 1, per_page: 50,
}

async function mountView(paginator = PAGINATOR) {
  vi.mocked(client.get).mockResolvedValue({ data: paginator })
  const w = mount(VariantsView, { global: { plugins: [router, setupManagerAuth()] } })
  await flushPromises()
  return w
}

describe('VariantsView', () => {
  beforeEach(() => vi.clearAllMocks())

  it('formats the variant price in major units (÷100 — regression)', async () => {
    const w = await mountView()
    // 420000 centimes → "4 200 XOF", NOT "420 000"
    expect(w.text()).toContain('4')
    expect(w.text()).toContain('200')
    expect(w.text()).not.toContain('420 000')
  })

  it('renders attribute chips from the JSON blob', async () => {
    const w = await mountView()
    const chips = w.findAll('.attr-chip').map(c => c.text())
    expect(chips).toContain('30L')
    expect(chips).toContain('Rouge')
  })

  it('shows per-variant available stock (3 / 5)', async () => {
    const w = await mountView()
    expect(w.find('.stock-qty').text()).toBe('3')
    expect(w.find('.stock-total').text()).toContain('5')
  })

  it('shows an empty state when there are no variants', async () => {
    const w = await mountView({ data: [], total: 0, current_page: 1, last_page: 1, per_page: 50 })
    expect(w.text()).toContain('Aucune variante')
  })
})
