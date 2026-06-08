import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import DeliveryListView from '@/modules/deliveries/views/DeliveryListView.vue'
import { setupManagerAuth } from '@/test-utils/setupAuth'
import { vFocusTrap } from '@/directives/focusTrap'
import { setLocale } from '@/i18n'
import client from '@/api/client'

// deliveryService.list → res.data (rows) + res.meta
const LIST = {
  data: [{
    id: 'd1', status: 'pending', order_id: 'o1', order_number: 'ORD-1',
    carrier: 'DHL', tracking_number: 'TRK1', created_at: '2026-05-01T10:00:00Z',
    dispatched_at: null, delivered_at: null, failed_at: null,
  }],
  meta: { current_page: 1, last_page: 1, per_page: 20, total: 1 },
}

async function mountView() {
  vi.mocked(client.get).mockResolvedValue({ data: LIST } as any)
  const w = mount(DeliveryListView, {
    global: {
      plugins: [setupManagerAuth()],
      directives: { 'focus-trap': vFocusTrap },               // BaseModal uses v-focus-trap
      stubs: { teleport: true, SalesTabNav: true, RouterLink: true },
    },
  })
  await flushPromises()
  return w
}

describe('DeliveryListView (i18n + BaseModal)', () => {
  beforeEach(() => { vi.clearAllMocks(); setLocale('fr') })
  afterEach(() => setLocale('fr'))

  it('renders deliveries with a translated status (FR by default)', async () => {
    const w = await mountView()
    expect(w.text()).toContain('Livraisons')   // title
    expect(w.text()).toContain('En attente')    // status: pending (FR)
  })

  it('opens the create modal in a shared BaseModal dialog (UX-03)', async () => {
    const w = await mountView()
    expect(w.find('[role="dialog"]').exists()).toBe(false)

    await w.findAll('button').find(b => b.text().includes('Nouvelle livraison'))!.trigger('click')
    await flushPromises()

    const dialog = w.find('[role="dialog"]')
    expect(dialog.exists()).toBe(true)
    expect(dialog.attributes('aria-modal')).toBe('true')
  })

  it('re-renders in English when the locale is switched (UX-13)', async () => {
    const w = await mountView()
    expect(w.text()).toContain('Livraisons')

    setLocale('en')
    await flushPromises()

    expect(w.text()).toContain('Deliveries')   // title
    expect(w.text()).toContain('Pending')      // status translated
  })
})
