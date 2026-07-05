import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import OrderDetailView from '@/modules/orders/views/OrderDetailView.vue'
import { setupManagerAuth } from '@/test-utils/setupAuth'
import client from '@/api/client'

// voidPayment() passe par useConfirm() (host ConfirmDialog dans App.vue, absent ici)
// → on mocke le composable pour auto-confirmer.
const { confirmMock } = vi.hoisted(() => ({ confirmMock: vi.fn(() => Promise.resolve(true)) }))
vi.mock('@/composables/useConfirm', () => ({ useConfirm: () => ({ confirm: confirmMock }) }))

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
// RC-5C — unités sérialisées rattachées à la commande.
const UNITS = {
  data: [{ id: 'u1', product_id: 'p1', variant_id: null, order_line_id: 'l1', customer_id: null, serial_type: 'imei', serial_value: '359000000000001', condition: 'new', status: 'reserved', sold_at: null }],
  count: 1,
}
// RC-5D — contrats de garantie générés pour la commande.
const WARRANTIES = {
  data: [{ id: 'w1', product_id: 'p1', inventory_unit_id: 'u1', order_line_id: 'l1', customer_id: null, serial_value: '359000000000001', starts_at: '2026-06-01T10:00:00Z', ends_at: '2027-06-01T10:00:00Z', status: 'active', product_name: 'iPhone 15', policy_name: 'Garantie 12 mois' }],
  count: 1,
}
// RC-5E — droits d'accès digitaux générés pour la commande.
const ENTITLEMENTS = {
  data: [{ id: 'e1', product_id: 'p1', order_line_id: 'l1', unit_index: 1, customer_id: null, fulfillment_type: 'license', status: 'active', granted_at: '2026-06-01T10:00:00Z', expires_at: null, product_name: 'Ebook PHP' }],
  count: 1,
}

// Dispatch client.get by URL (order / payments / deliveries / units / warranties / entitlements / claims)
function mockGet(units: any = UNITS, warranties: any = WARRANTIES, entitlements: any = ENTITLEMENTS, claims: any = { data: [], count: 0 }) {
  vi.mocked(client.get).mockImplementation((url: string) => {
    if (url.endsWith('/payments'))     return Promise.resolve({ data: PAYMENTS }) as any
    if (url.endsWith('/deliveries'))   return Promise.resolve({ data: { data: [] } }) as any
    if (url.endsWith('/units'))        return Promise.resolve({ data: units }) as any
    if (url.endsWith('/entitlements')) return Promise.resolve({ data: entitlements }) as any
    if (url.endsWith('/claims'))       return Promise.resolve({ data: claims }) as any   // before /warranties/ catch-all
    if (url.includes('/warranties/'))  return Promise.resolve({ data: warranties }) as any
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
    // Confirm dialog → accept (useConfirm mocké en tête de fichier)
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

  it('lists the serialized units (IMEI) attached to the order with their status', async () => {
    const w = await mountView()
    expect(w.text()).toContain('Unités sérialisées')
    expect(w.text()).toContain('359000000000001')
    expect(w.text()).toContain('IMEI')
    expect(w.text()).toContain('Réservée')
  })

  it('hides the units panel when the order has no serialized units', async () => {
    mockGet({ data: [], count: 0 })
    const w = await mountView()
    expect(w.text()).not.toContain('Unités sérialisées')
  })

  it('lists the warranty contracts generated for the order', async () => {
    const w = await mountView()
    expect(w.text()).toContain('Garanties')
    expect(w.text()).toContain('Garantie 12 mois')
    expect(w.text()).toContain('Active')
  })

  it('hides the warranties panel when the order has no contracts', async () => {
    mockGet(UNITS, { data: [], count: 0 })
    const w = await mountView()
    expect(w.text()).not.toContain('Garanties')
  })

  it('lists the digital access entitlements generated for the order', async () => {
    const w = await mountView()
    expect(w.text()).toContain('Accès digital')
    expect(w.text()).toContain('Ebook PHP')
    expect(w.text()).toContain('Licence')
  })

  it('hides the digital access panel when there are no entitlements', async () => {
    mockGet(UNITS, WARRANTIES, { data: [], count: 0 })
    const w = await mountView()
    expect(w.text()).not.toContain('Accès digital')
  })

  // RC-7D — un accès par exemplaire : le rang d'exemplaire n'apparaît que si la ligne en a plusieurs.
  it('shows the per-unit rank when a line grants several accesses', async () => {
    const perUnit = { data: [
      { id: 'e1', product_id: 'p1', order_line_id: 'l1', unit_index: 1, customer_id: null, fulfillment_type: 'license', status: 'active', granted_at: '2026-06-01T10:00:00Z', expires_at: null, product_name: 'Ebook PHP' },
      { id: 'e2', product_id: 'p1', order_line_id: 'l1', unit_index: 2, customer_id: null, fulfillment_type: 'license', status: 'revoked', granted_at: '2026-06-01T10:00:00Z', expires_at: null, product_name: 'Ebook PHP' },
    ], count: 2 }
    mockGet(UNITS, WARRANTIES, perUnit)
    const w = await mountView()
    expect(w.text()).toContain('Exemplaire n°1')
    expect(w.text()).toContain('Exemplaire n°2')
  })

  it('offers to open an after-sales claim on an active warranty without a claim', async () => {
    const w = await mountView()
    expect(w.text()).toContain('Ouvrir un SAV')
  })

  it('shows the claim status when a claim exists for the contract', async () => {
    mockGet(UNITS, WARRANTIES, ENTITLEMENTS, {
      data: [{ id: 'c1', warranty_contract_id: 'w1', reason: 'defect', status: 'in_repair', out_of_warranty: false }],
      count: 1,
    })
    const w = await mountView()
    expect(w.text()).toContain('En réparation')
    expect(w.text()).not.toContain('Ouvrir un SAV')
  })
})
