import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import OrderDetailView from '@/modules/orders/views/OrderDetailView.vue'
import { setupManagerAuth } from '@/test-utils/setupAuth'
import client from '@/api/client'

const router = createRouter({
  history: createMemoryHistory(),
  routes: [
    { path: '/orders', name: 'orders.list', component: { template: '<div/>' } },
    { path: '/orders/:id', name: 'orders.show', component: { template: '<div/>' } },
    { path: '/deliveries', name: 'deliveries.list', component: { template: '<div/>' } },
    { path: '/inventory/movements/:productId', name: 'inventory.movements', component: { template: '<div/>' } },
  ],
})

const ORDER = {
  id: 'order-1', number: 'ORD-00001', status: 'confirmed', currency: 'XOF',
  total_amount: 1500000, // 15 000 XOF in centimes
  note: null, created_at: '2026-06-01T10:00:00Z',
  lines: [{ id: 'l1', sku: 'RIZ-0001', name: 'Riz', quantity: 3, unit_price_cents: 500000, product_id: 'p1' }],
}
const PAYMENTS = {
  data: [{ id: 'pay-1', method: 'cash', amount_cents: 500000, currency: 'XOF', reference: 'R1', paid_at: '2026-06-01T11:00:00Z' }],
  balance: 500000, is_fully_paid: false,
}

// Dispatch client.get by URL (order / payments / deliveries)
function mockGet() {
  vi.mocked(client.get).mockImplementation((url: string) => {
    if (url.endsWith('/payments'))   return Promise.resolve({ data: PAYMENTS }) as any
    if (url.endsWith('/deliveries')) return Promise.resolve({ data: { data: [] } }) as any
    return Promise.resolve({ data: ORDER }) as any // the order itself
  })
}

async function mountView() {
  await router.push('/orders/order-1')
  const w = mount(OrderDetailView, { global: { plugins: [router, setupManagerAuth()] } })
  await flushPromises()
  return w
}

describe('OrderDetailView', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    mockGet()
  })

  it('renders order number, lines and total formatted in major units (÷100)', async () => {
    const w = await mountView()
    expect(w.text()).toContain('ORD-00001')
    // 1 500 000 centimes → "15 000 XOF" (NOT 1 500 000)
    expect(w.text()).toContain('15')
    expect(w.text()).toContain('000')
    expect(w.text()).not.toContain('1 500 000')
  })

  it('shows the payment balance (encaissé / reste)', async () => {
    const w = await mountView()
    // Paid 5 000, total 15 000 → reste 10 000
    expect(w.text()).toContain('5')   // encaissé 5 000
    expect(w.text()).toContain('10')  // reste 10 000
  })

  it('surfaces an error when voiding a payment fails (was silently swallowed)', async () => {
    const w = await mountView()
    // Confirm dialog → accept
    vi.spyOn(window, 'confirm').mockReturnValue(true)
    // Void rejects with a backend message
    vi.mocked(client.delete).mockRejectedValue({ response: { data: { message: 'Paiement déjà annulé.' } } })

    const voidBtn = w.find('.pay-item .btn-icon-sm')
    expect(voidBtn.exists()).toBe(true)
    await voidBtn.trigger('click')
    await flushPromises()

    // The error must now be visible (regression: it used to be catch { /* ignore */ })
    expect(w.text()).toContain('déjà annulé')
  })

  it('shows the Fulfill action for a confirmed order', async () => {
    const w = await mountView()
    expect(w.text()).toContain('Marquer livrée')
  })
})
