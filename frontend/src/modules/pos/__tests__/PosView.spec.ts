import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import PosView from '@/modules/pos/views/PosView.vue'
import { setupManagerAuth } from '@/test-utils/setupAuth'
import { vFocusTrap } from '@/directives/focusTrap'

// Mock the orchestrated services directly — PosView is a composition of these.
// vi.hoisted lets the mock objects exist before the hoisted vi.mock factories run.
const { posService, productService } = vi.hoisted(() => ({
  posService: { current: vi.fn(), open: vi.fn(), checkout: vi.fn(), close: vi.fn(), list: vi.fn() },
  productService: { list: vi.fn(), getDetail: vi.fn(), getBySku: vi.fn() },
}))
vi.mock('@/modules/pos/services/posService', () => ({ posService }))
vi.mock('@/modules/catalog/services/productService', () => ({ productService }))

const SESSION = {
  id: 'sess-1', status: 'open', label: 'Caisse 1', warehouse_id: null,
  opening_float_cents: 1000000, total_sales_cents: 0, cash_sales_cents: 0,
  sales_count: 0, expected_cash_cents: 1000000, counted_cash_cents: null,
  difference_cents: null, opened_by: 'u1', closed_by: null,
  opened_at: '2026-06-04T08:00:00Z', closed_at: null, notes: null,
}

const SIMPLE_PRODUCT = {
  id: 'p1', name: 'Savon de Marseille', sku: 'SAV-1',
  has_variants: false, price: { amount: 25000, currency: 'XOF' },
}

function mountView() {
  return mount(PosView, {
    global: {
      plugins: [setupManagerAuth()],
      directives: { 'focus-trap': vFocusTrap },  // modals now use BaseModal (v-focus-trap)
      stubs: { teleport: true },                 // render BaseModal's Teleport inline
    },
  })
}

async function mountWithSession() {
  posService.current.mockResolvedValue(SESSION)
  const w = mountView()
  await flushPromises()
  return w
}

// Add a product to the cart through the scanner path (Enter key — no debounce).
async function scan(w: any, product = SIMPLE_PRODUCT) {
  productService.getBySku.mockResolvedValue(product)
  const search = w.find('[data-test="product-search"]')
  await search.setValue(product.sku)
  await search.trigger('keyup.enter')
  await flushPromises()
}

describe('PosView', () => {
  beforeEach(() => vi.clearAllMocks())

  it('shows the open-session screen when no session is open', async () => {
    posService.current.mockResolvedValue(null)
    const w = mountView()
    await flushPromises()

    expect(w.find('[data-test="open-session"]').exists()).toBe(true)
    expect(w.find('[data-test="product-search"]').exists()).toBe(false)
  })

  it('opens a session with the entered float converted to centimes (×100)', async () => {
    posService.current.mockResolvedValue(null)
    posService.open.mockResolvedValue(SESSION)
    const w = mountView()
    await flushPromises()

    await w.find('[data-test="opening-float"]').setValue(5000)   // 5 000 XOF
    await w.find('[data-test="open-session"]').trigger('click')
    await flushPromises()

    expect(posService.open).toHaveBeenCalledWith({ opening_float_cents: 500000 })
    // Terminal now visible
    expect(w.find('[data-test="product-search"]').exists()).toBe(true)
  })

  it('adds a scanned product to the cart and totals it in major units (÷100)', async () => {
    const w = await mountWithSession()
    await scan(w)

    expect(productService.getBySku).toHaveBeenCalledWith('SAV-1')
    expect(w.find('[data-test="qty-0"]').text()).toBe('1')
    // 25 000 centimes → "250", never the raw cents "25 000"
    const total = w.find('[data-test="cart-total"]').text().replace(/\s/g, '')
    expect(total).toContain('250')
    expect(total).not.toContain('25000')
  })

  it('checks out the cart and clears it on success', async () => {
    const w = await mountWithSession()
    await scan(w)
    posService.checkout.mockResolvedValue({
      order: { id: 'o1', total_amount: 25000 },
      payment: { id: 'pay1', amount_cents: 25000, method: 'cash' },
      session: { ...SESSION, sales_count: 1, total_sales_cents: 25000, cash_sales_cents: 25000 },
    })

    await w.find('[data-test="checkout"]').trigger('click')
    await flushPromises()

    expect(posService.checkout).toHaveBeenCalledTimes(1)
    const [sessionId, payload] = posService.checkout.mock.calls[0]
    expect(sessionId).toBe('sess-1')
    expect(payload.method).toBe('cash')
    expect(payload.items).toEqual([{ product_id: 'p1', variant_id: undefined, quantity: 1 }])
    // Cart cleared
    expect(w.find('[data-test="qty-0"]').exists()).toBe(false)
    expect(w.find('[data-test="toast"]').exists()).toBe(true)
  })

  it('closes the session with the counted cash for reconciliation', async () => {
    const w = await mountWithSession()
    posService.close.mockResolvedValue({ ...SESSION, status: 'closed', counted_cash_cents: 990000, difference_cents: -10000 })

    await w.find('[data-test="open-close-modal"]').trigger('click')
    await flushPromises()
    // Modal prefills counted = expected (10 000 XOF major units)
    await w.find('[data-test="counted-cash"]').setValue(9900)   // counts 9 900 → short
    await w.find('[data-test="confirm-close"]').trigger('click')
    await flushPromises()

    expect(posService.close).toHaveBeenCalledWith('sess-1', { counted_cash_cents: 990000 })
    // Back to the open-session screen
    expect(w.find('[data-test="open-session"]').exists()).toBe(true)
  })
})
