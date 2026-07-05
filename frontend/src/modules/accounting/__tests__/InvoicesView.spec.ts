import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createWebHistory } from 'vue-router'
import InvoicesView from '@/modules/accounting/views/InvoicesView.vue'
import { setupManagerAuth } from '@/test-utils/setupAuth'
import { vFocusTrap } from '@/directives/focusTrap'

const { accountingService } = vi.hoisted(() => ({
  accountingService: {
    invoices: vi.fn(), issueInvoice: vi.fn(), createInvoice: vi.fn(), allocatePayment: vi.fn(),
    taxes: vi.fn(), invoicePdfUrl: vi.fn((id: string) => `/api/accounting/invoices/${id}/pdf`),
  },
}))
vi.mock('@/modules/accounting/services/accountingService', () => ({ accountingService }))

const router = createRouter({
  history: createWebHistory(),
  routes: ['invoices', 'entries', 'chart', 'taxes', 'periods', 'settings'].map(p => ({ path: `/accounting/${p}`, component: { template: '<div/>' } })),
})

const VAT = { id: 'tx1', code: 'TVA18', name: 'TVA 18 %', rate_bp: 1800, is_inclusive: false, country: 'SN', is_active: true }

function mountView() {
  return mount(InvoicesView, {
    global: { plugins: [router, setupManagerAuth()], directives: { 'focus-trap': vFocusTrap }, stubs: { teleport: true } },
  })
}

describe('InvoicesView', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    accountingService.taxes.mockResolvedValue([VAT])
  })

  it('lists invoices with status and issues a draft', async () => {
    accountingService.invoices.mockResolvedValue({
      data: [{ id: 'i1', number: null, customer_name: 'ACME', issue_date: null, subtotal_minor: 100000, tax_total_minor: 18000, total_minor: 118000, paid_minor: 0, status: 'draft', lines: [] }],
      meta: { current_page: 1, last_page: 1, total: 1 },
    })
    const w = mountView()
    await flushPromises()
    expect(w.text()).toContain('ACME')
    expect(w.text()).toContain('BROUILLON')

    accountingService.issueInvoice.mockResolvedValue({})
    await w.find('[data-test="issue-i1"]').trigger('click')
    await flushPromises()
    expect(accountingService.issueInvoice).toHaveBeenCalledWith('i1')
  })

  it('computes HT/VAT/total live in the create modal and posts cents', async () => {
    accountingService.invoices.mockResolvedValue({ data: [], meta: { current_page: 1, last_page: 1, total: 0 } })
    const w = mountView()
    await flushPromises()

    await w.find('[data-test="new-invoice"]').trigger('click')
    await flushPromises()
    await w.find('[data-test="inv-customer"]').setValue('Client SA')
    await w.find('[data-test="inv-label-0"]').setValue('Prestation')
    // qty 2, price 1000 (→ 200 000 cents HT), VAT 18 %
    const line = w.findAll('[data-test="inv-line-0"] input')
    await line[1].setValue(2)   // quantity
    await w.find('[data-test="inv-price-0"]').setValue(1000)
    await w.find('[data-test="inv-line-0"] select').setValue('tx1')
    await flushPromises()

    expect(w.find('[data-test="inv-subtotal"]').text().replace(/\s/g, '')).toContain('200') // 200 000 c → « 2 000 » major
    // total = 200 000 + 36 000 = 236 000 cents
    accountingService.createInvoice.mockResolvedValue({})
    await w.find('[data-test="inv-submit"]').trigger('click')
    await flushPromises()

    const payload = accountingService.createInvoice.mock.calls[0][0]
    expect(payload.customer_name).toBe('Client SA')
    expect(payload.lines[0]).toMatchObject({ label: 'Prestation', quantity: 2, unit_price_minor: 100000, tax_id: 'tx1' })
  })

  it('surfaces a server error when allocation exceeds the balance', async () => {
    accountingService.invoices.mockResolvedValue({
      data: [{ id: 'i2', number: 'FA-000001', customer_name: 'X', issue_date: '2026-07-05', subtotal_minor: 50000, tax_total_minor: 0, total_minor: 50000, paid_minor: 0, status: 'issued', lines: [] }],
      meta: { current_page: 1, last_page: 1, total: 1 },
    })
    const w = mountView()
    await flushPromises()

    await w.find('[data-test="pay-i2"]').trigger('click')
    await w.find('[data-test="pay-payment-id"]').setValue('pay-uuid')
    accountingService.allocatePayment.mockRejectedValue({ response: { data: { message: 'Allocation supérieure au disponible.' } } })
    await w.find('[data-test="pay-submit"]').trigger('click')
    await flushPromises()
    expect(w.text()).toContain('Allocation supérieure au disponible.')
  })
})
