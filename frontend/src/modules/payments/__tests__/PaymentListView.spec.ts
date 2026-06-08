import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import PaymentListView from '@/modules/payments/views/PaymentListView.vue'
import { setupManagerAuth } from '@/test-utils/setupAuth'
import { vFocusTrap } from '@/directives/focusTrap'
import { setLocale } from '@/i18n'
import client from '@/api/client'

const router = createRouter({
  history: createMemoryHistory(),
  routes: [
    { path: '/orders', name: 'orders.list', component: { template: '<div/>' } },
    { path: '/orders/:id', name: 'orders.show', component: { template: '<div/>' } },
    { path: '/payments', name: 'payments.list', component: { template: '<div/>' } },
    { path: '/:pathMatch(.*)*', component: { template: '<div/>' } },
  ],
})

// paymentService.list does `.then(r => r.data)`, view reads res.data + res.meta
// → /api/payments returns a Resource collection { data: [...], meta: {...} }
const PAYMENTS = {
  data: [
    {
      id: 'pay1', paid_at: '2026-05-20T09:00:00Z',
      order_id: 'ord-9', order_number: 'ORD-00009',
      method: 'mobile_money', reference: 'OM-77123',
      amount_cents: 1234500, currency: 'XOF',
    },
  ],
  meta: { current_page: 1, last_page: 1, per_page: 20, total: 1 },
}

function mockApi(page = PAYMENTS) {
  vi.mocked(client.get).mockResolvedValue({ data: page } as any)
  vi.mocked(client.delete).mockResolvedValue({ data: {} } as any)
}

async function mountView(page = PAYMENTS) {
  mockApi(page)
  const w = mount(PaymentListView, {
    global: {
      plugins: [router, setupManagerAuth()],
      directives: { 'focus-trap': vFocusTrap },  // BaseModal uses v-focus-trap
      stubs: { teleport: true },                 // render BaseModal's Teleport inline
    },
  })
  await flushPromises()
  return w
}

const noSpaces = (s: string) => s.replace(/\s/g, '')

describe('PaymentListView', () => {
  beforeEach(() => { vi.clearAllMocks(); setLocale('fr') })
  afterEach(() => setLocale('fr'))

  it('renders payments with method label, reference and amount in major units (÷100)', async () => {
    const w = await mountView()
    expect(w.text()).toContain('Mobile Money')
    expect(w.text()).toContain('OM-77123')
    // 1 234 500 centimes → "12 345 …", NOT the raw cents
    const flat = noSpaces(w.text())
    expect(flat).toContain('12345')
    expect(flat).not.toContain('1234500')
  })

  it('links a payment to its originating order', async () => {
    const w = await mountView()
    const link = w.find('a[href="/orders/ord-9"]')
    expect(link.exists()).toBe(true)
    expect(link.text()).toContain('ORD-00009')
  })

  it('voids a payment via the service after confirmation', async () => {
    const confirmSpy = vi.spyOn(window, 'confirm').mockReturnValue(true)
    const w = await mountView()
    await w.findAll('button').find(b => b.text().includes('Annuler'))!.trigger('click')
    await flushPromises()
    expect(confirmSpy).toHaveBeenCalled()
    expect(client.delete).toHaveBeenCalledWith('/api/payments/pay1')
    confirmSpy.mockRestore()
  })

  it('shows an empty state when there are no payments', async () => {
    const w = await mountView({ data: [], meta: { current_page: 1, last_page: 1, per_page: 20, total: 0 } })
    expect(w.text()).toContain('Les paiements enregistrés apparaîtront ici')
  })

  it('exposes the mobile card-stacking contract (UX-06)', async () => {
    const w = await mountView()
    const table = w.find('table.data-table')
    expect(table.classes()).toContain('data-table--cards')

    // Every body cell must be labelled (data-label → shown as the column header on mobile)
    // or be a structural identity/actions cell — otherwise it would render unlabelled in a card.
    const cells = table.findAll('tbody td')
    expect(cells.length).toBeGreaterThan(0)
    for (const td of cells) {
      const labelled   = td.attributes('data-label') !== undefined
      const structural = td.classes().includes('cell-primary') || td.classes().includes('cell-actions')
      expect(labelled || structural).toBe(true)
    }
    expect(table.find('td.cell-actions').exists()).toBe(true)
  })

  it('opens the record-payment modal in a shared BaseModal dialog (UX-03)', async () => {
    const w = await mountView()
    expect(w.find('[role="dialog"]').exists()).toBe(false)

    await w.findAll('button').find(b => b.text().includes('Nouveau paiement'))!.trigger('click')
    await flushPromises()

    const dialog = w.find('[role="dialog"]')
    expect(dialog.exists()).toBe(true)
    expect(dialog.attributes('aria-modal')).toBe('true')           // BaseModal a11y contract
    expect(dialog.text()).toContain('Enregistrer un paiement')     // title rendered by BaseModal
  })

  it('re-renders in English when the locale is switched (i18n — UX-13)', async () => {
    const w = await mountView()
    expect(w.text()).toContain('Paiements')   // French by default

    setLocale('en')
    await flushPromises()

    expect(w.text()).toContain('Payments')
    expect(w.text()).toContain('New payment')
  })
})
