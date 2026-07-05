import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import PosDesktopView from '@/modules/pos/views/PosDesktopView.vue'
import { setupManagerAuth } from '@/test-utils/setupAuth'
import { vFocusTrap } from '@/directives/focusTrap'

const { posService, productService } = vi.hoisted(() => ({
  posService: { current: vi.fn(), open: vi.fn(), checkout: vi.fn(), close: vi.fn(), list: vi.fn(), cashMovement: vi.fn(), refund: vi.fn(), movements: vi.fn() },
  productService: { list: vi.fn(), getDetail: vi.fn(), getBySku: vi.fn() },
}))
vi.mock('@/modules/pos/services/posService', () => ({ posService }))
vi.mock('@/modules/catalog/services/productService', () => ({ productService }))

const SESSION = {
  id: 'sess-1', status: 'open', label: 'Caisse 1', warehouse_id: null,
  opening_float_cents: 0, total_sales_cents: 0, cash_sales_cents: 0, sales_count: 0,
  net_cash_movements_cents: 0, expected_cash_cents: 0, counted_cash_cents: null,
  difference_cents: null, opened_by: 'u1', closed_by: null,
  opened_at: '2026-07-05T08:00:00Z', closed_at: null, notes: null,
}
const PRODUCT = { id: 'p1', name: 'Savon', sku: 'SAV-1', has_variants: false, price: { amount: 25000, currency: 'XOF' } }

function mountView() {
  return mount(PosDesktopView, {
    global: { plugins: [setupManagerAuth()], directives: { 'focus-trap': vFocusTrap }, stubs: { teleport: true } },
  })
}
async function mountWithSession() {
  posService.current.mockResolvedValue(SESSION)
  const w = mountView()
  await flushPromises()
  return w
}
async function scan(w: any, product = PRODUCT) {
  productService.getBySku.mockResolvedValue(product)
  const search = w.find('[data-test="product-search"]')
  await search.setValue(product.sku)
  await search.trigger('keyup.enter')
  await flushPromises()
}

describe('PosDesktopView', () => {
  beforeEach(() => { vi.clearAllMocks() })

  it('settles a split payment whose legs sum to the total', async () => {
    const w = await mountWithSession()
    await scan(w)                                   // 25 000 c total (250 major)

    // Leg 1: 100 (major) cash → 10 000 c
    await w.find('[data-test="pay-method"]').setValue('cash')
    await w.find('[data-test="leg-amount"]').setValue(100)
    await w.find('[data-test="add-leg"]').trigger('click')
    // Leg 2: 150 (major) mobile money → 15 000 c → remaining 0
    await w.find('[data-test="pay-method"]').setValue('mobile_money')
    await w.find('[data-test="leg-amount"]').setValue(150)
    await w.find('[data-test="add-leg"]').trigger('click')

    expect(w.find('[data-test="remaining"]').text().replace(/\s/g, '')).toContain('0')

    posService.checkout.mockResolvedValue({
      order: { id: 'o1', number: 'CMD-1', total_amount: 25000, lines: [{ id: 'ol1', quantity: 1, product_name: 'Savon' }] },
      payments: [{}, {}], payment: {}, session: { ...SESSION, sales_count: 1 },
    })

    await w.find('[data-test="checkout-split"]').trigger('click')
    await flushPromises()

    const [sessionId, payload] = posService.checkout.mock.calls[0]
    expect(sessionId).toBe('sess-1')
    expect(payload.payments).toEqual([
      { method: 'cash', amount_cents: 10000 },
      { method: 'mobile_money', amount_cents: 15000 },
    ])
    expect(w.find('[data-test="toast"]').exists()).toBe(true)
  })

  it('records a cash-drawer movement', async () => {
    const w = await mountWithSession()
    posService.cashMovement.mockResolvedValue({ movement: { id: 'm1', direction: 'in', amount_cents: 5000 }, session: { ...SESSION, net_cash_movements_cents: 5000, expected_cash_cents: 5000 } })

    await w.find('[data-test="open-movement"]').trigger('click')
    await flushPromises()
    await w.find('[data-test="mv-amount"]').setValue(50)     // 50 major → 5 000 c
    await w.find('[data-test="mv-confirm"]').trigger('click')
    await flushPromises()

    const [sessionId, payload] = posService.cashMovement.mock.calls[0]
    expect(sessionId).toBe('sess-1')
    expect(payload).toMatchObject({ direction: 'in', amount_cents: 5000, reason: 'float_add' })
  })

  it('refunds the last sale', async () => {
    const w = await mountWithSession()
    await scan(w)
    posService.checkout.mockResolvedValue({
      order: { id: 'o1', number: 'CMD-9', total_amount: 25000, lines: [{ id: 'ol1', quantity: 1, product_name: 'Savon' }] },
      payments: [{}], payment: {}, session: { ...SESSION, sales_count: 1, cash_sales_cents: 25000, expected_cash_cents: 25000 },
    })
    await w.find('[data-test="quick-cash"]').trigger('click')
    await flushPromises()

    // Last sale captured → refund button enabled.
    await w.find('[data-test="open-refund"]').trigger('click')
    await flushPromises()
    await w.find('[data-test="refund-reason"]').setValue('Client insatisfait')

    posService.refund.mockResolvedValue({
      return: { id: 'r1', number: 'RET-1', status: 'restocked', refund_amount_cents: 25000 },
      movement: { id: 'm1', direction: 'out', amount_cents: 25000 },
      session: { ...SESSION, expected_cash_cents: 0 },
    })

    await w.find('[data-test="refund-confirm"]').trigger('click')
    await flushPromises()

    const [sessionId, payload] = posService.refund.mock.calls[0]
    expect(sessionId).toBe('sess-1')
    expect(payload).toEqual({
      order_id: 'o1',
      lines: [{ order_line_id: 'ol1', quantity: 1 }],
      reason: 'Client insatisfait',
      refund_method: 'cash',
    })
  })
})
