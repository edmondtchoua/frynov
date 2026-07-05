import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import OrderCreateView from '@/modules/orders/views/OrderCreateView.vue'
import { setupManagerAuth } from '@/test-utils/setupAuth'
import client from '@/api/client'

// Stub router — OrderCreateView calls router.push on success
const router = createRouter({
  history: createMemoryHistory(),
  routes: [
    { path: '/orders', name: 'orders.list', component: { template: '<div/>' } },
    { path: '/orders/new', name: 'orders.create', component: { template: '<div/>' } },
    { path: '/orders/:id', name: 'orders.show', component: { template: '<div/>' } },
  ],
})

const SIMPLE_PRODUCT = {
  id: 'prod-simple', name: 'Riz parfumé 25kg', sku: 'RIZ-0001',
  has_variants: false, price: { amount: 1800000, currency: 'XOF', formatted: '18 000 XOF' },
}
const VARIABLE_PRODUCT = {
  id: 'prod-var', name: 'Bassine', sku: 'BAS-0015',
  has_variants: true, price: { amount: 420000, currency: 'XOF', formatted: '4 200 XOF' },
}

function mountView() {
  return mount(OrderCreateView, { global: { plugins: [router, setupManagerAuth()] } })
}

describe('OrderCreateView', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    // Default product search returns the simple product
    vi.mocked(client.get).mockResolvedValue({ data: { data: [SIMPLE_PRODUCT] } })
  })

  it('renders one empty line item by default', () => {
    const w = mountView()
    expect(w.findAll('.line-row').length).toBe(1)
  })

  it('selecting a simple product fills the line and computes the total', async () => {
    const w = mountView()
    // Focus the product input → triggers loadProducts
    await w.find('.line-row .picker input').trigger('focus')
    await flushPromises()
    // Dropdown shows the product
    const option = w.find('.picker-option')
    expect(option.exists()).toBe(true)
    await option.trigger('click')
    await flushPromises()
    // Order total reflects the product price (18 000 XOF for qty 1)
    expect(w.find('.ot-value').text()).toContain('18')
    expect(w.find('.ot-value').text()).toContain('000')
  })

  it('requires a variant for a variable product before submit', async () => {
    const w = mountView()
    vi.mocked(client.get).mockResolvedValueOnce({ data: { data: [VARIABLE_PRODUCT] } })
    await w.find('.line-row .picker input').trigger('focus')
    await flushPromises()
    // getDetail call for variants returns one active variant
    vi.mocked(client.get).mockResolvedValueOnce({
      data: { data: { ...VARIABLE_PRODUCT, variants: [
        { id: 'v1', label: '30L / Rouge', sku: 'BAS-0015-V1', is_active: true, price: { amount: 500000 } },
      ] } },
    })
    await w.find('.picker-option').trigger('click')
    await flushPromises()
    // A variant selector appears
    expect(w.find('.variant-select').exists()).toBe(true)
    // Submitting without choosing a variant is blocked with an error
    await w.find('form').trigger('submit')
    await flushPromises()
    expect(w.text()).toContain('déclinaison')
    expect(vi.mocked(client.post)).not.toHaveBeenCalled()
  })

  it('submits the correct payload (product_id + quantity, no unit price)', async () => {
    const w = mountView()
    vi.mocked(client.post).mockResolvedValue({ data: { id: 'order-123' } })

    await w.find('.line-row .picker input').trigger('focus')
    await flushPromises()
    await w.find('.picker-option').trigger('click')
    await flushPromises()

    await w.find('form').trigger('submit')
    await flushPromises()

    expect(vi.mocked(client.post)).toHaveBeenCalledTimes(1)
    const [url, payload] = vi.mocked(client.post).mock.calls[0]
    expect(url).toBe('/api/orders')
    expect(payload).toMatchObject({
      items: [{ product_id: 'prod-simple', quantity: 1 }],
    })
    // SECURITY: the client never sends a price — backend resolves it
    expect((payload as any).items[0]).not.toHaveProperty('unit_price_cents')
  })

  it('disables submit when no valid line exists', () => {
    const w = mountView()
    const submitBtn = w.find('button[type="submit"]')
    expect(submitBtn.attributes('disabled')).toBeDefined()
  })
})
