import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import PosMobileView from '@/modules/pos/views/PosMobileView.vue'
import { usePosOfflineQueue } from '@/modules/pos/composables/posOfflineQueue'
import { setupManagerAuth } from '@/test-utils/setupAuth'
import { vFocusTrap } from '@/directives/focusTrap'

const { posService, productService } = vi.hoisted(() => ({
  posService: { current: vi.fn(), open: vi.fn(), checkout: vi.fn(), close: vi.fn(), list: vi.fn(), cashMovement: vi.fn(), refund: vi.fn(), movements: vi.fn() },
  productService: { list: vi.fn(), getDetail: vi.fn(), getBySku: vi.fn() },
}))
vi.mock('@/modules/pos/services/posService', () => ({ posService }))
vi.mock('@/modules/catalog/services/productService', () => ({ productService }))

const SESSION = {
  id: 'sess-1', status: 'open', label: 'M', warehouse_id: null,
  opening_float_cents: 0, total_sales_cents: 0, cash_sales_cents: 0, sales_count: 0,
  net_cash_movements_cents: 0, expected_cash_cents: 0, counted_cash_cents: null,
  difference_cents: null, opened_by: 'u1', closed_by: null,
  opened_at: '2026-07-05T08:00:00Z', closed_at: null, notes: null,
}
const PRODUCT = { id: 'p1', name: 'Savon', sku: 'SAV-1', has_variants: false, price: { amount: 25000, currency: 'XOF' } }

function mountView() {
  return mount(PosMobileView, {
    global: { plugins: [setupManagerAuth()], directives: { 'focus-trap': vFocusTrap }, stubs: { teleport: true } },
  })
}
async function mountWithSession() {
  posService.current.mockResolvedValue(SESSION)
  const w = mountView()
  await flushPromises()
  return w
}
async function scan(w: any) {
  productService.getBySku.mockResolvedValue(PRODUCT)
  const search = w.find('[data-test="product-search"]')
  await search.setValue(PRODUCT.sku)
  await search.trigger('keyup.enter')
  await flushPromises()
}

describe('PosMobileView', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    localStorage.clear()
    usePosOfflineQueue().clear()
  })

  it('rings up a Mobile Money sale online', async () => {
    const w = await mountWithSession()
    await scan(w)
    await w.find('[data-test="open-pay"]').trigger('click')
    await w.find('[data-test="pay-reference"]').setValue('OM-123')

    posService.checkout.mockResolvedValue({
      order: { id: 'o1', total_amount: 25000, lines: [] }, payments: [{}], payment: {}, session: { ...SESSION, sales_count: 1 },
    })
    await w.find('[data-test="pay-confirm"]').trigger('click')
    await flushPromises()

    const [sessionId, payload] = posService.checkout.mock.calls[0]
    expect(sessionId).toBe('sess-1')
    expect(payload).toMatchObject({ method: 'mobile_money', reference: 'OM-123' })
    expect(w.find('[data-test="toast"]').exists()).toBe(true)
  })

  it('queues the sale offline when the network fails', async () => {
    const w = await mountWithSession()
    await scan(w)
    await w.find('[data-test="open-pay"]').trigger('click')

    // Network error → axios error has no `response`.
    posService.checkout.mockRejectedValue(new Error('Network Error'))
    await w.find('[data-test="pay-confirm"]').trigger('click')
    await flushPromises()

    // Sale parked locally; the sync affordance appears.
    expect(usePosOfflineQueue().queue.value).toHaveLength(1)
    expect(w.find('[data-test="sync"]').exists()).toBe(true)
    expect(w.find('[data-test="toast"]').exists()).toBe(true)
  })

  it('flushes the offline queue on sync', async () => {
    // Seed one queued sale before mounting.
    usePosOfflineQueue().enqueue(
      { session_id: 'sess-1', total_cents: 25000, payload: { items: [{ product_id: 'p1', quantity: 1 }], method: 'mobile_money' } },
      'q-1', '2026-07-05T10:00:00Z',
    )

    const w = await mountWithSession()
    expect(w.find('[data-test="sync"]').exists()).toBe(true)

    posService.checkout.mockResolvedValue({ order: { id: 'o1', total_amount: 25000, lines: [] }, payments: [{}], payment: {}, session: SESSION })
    await w.find('[data-test="sync"]').trigger('click')
    await flushPromises()

    const [sessionId, payload] = posService.checkout.mock.calls[0]
    expect(sessionId).toBe('sess-1')
    expect(payload.method).toBe('mobile_money')
    expect(usePosOfflineQueue().queue.value).toHaveLength(0)
  })
})
