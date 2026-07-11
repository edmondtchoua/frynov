import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createWebHistory } from 'vue-router'
import CreditNotesView from '@/modules/accounting/views/CreditNotesView.vue'
import { setupManagerAuth } from '@/test-utils/setupAuth'
import { vFocusTrap } from '@/directives/focusTrap'

const { accountingService } = vi.hoisted(() => ({
  accountingService: {
    creditNotes: vi.fn(), invoices: vi.fn(), createCreditNote: vi.fn(),
    issueCreditNote: vi.fn(), applyCreditNote: vi.fn(),
    downloadInvoicePdf: vi.fn(() => Promise.resolve(new Blob())),
  },
}))
vi.mock('@/modules/accounting/services/accountingService', () => ({ accountingService }))

const router = createRouter({
  history: createWebHistory(),
  routes: ['invoices', 'credit-notes', 'entries', 'chart', 'taxes', 'periods', 'settings'].map(p => ({ path: `/accounting/${p}`, component: { template: '<div/>' } })),
})

function mountView() {
  return mount(CreditNotesView, {
    global: { plugins: [router, setupManagerAuth()], directives: { 'focus-trap': vFocusTrap }, stubs: { teleport: true } },
  })
}

const emptyPage = { data: [], meta: { current_page: 1, last_page: 1, total: 0 } }

describe('CreditNotesView', () => {
  beforeEach(() => vi.clearAllMocks())

  it('lists credit notes with status and issues a draft', async () => {
    accountingService.creditNotes.mockResolvedValue({
      data: [{ id: 'cn1', number: null, customer_name: 'ACME', issue_date: null, subtotal_minor: 100000, tax_total_minor: 18000, total_minor: 118000, paid_minor: 0, status: 'draft', kind: 'credit_note', lines: [] }],
      meta: { current_page: 1, last_page: 1, total: 1 },
    })
    const w = mountView()
    await flushPromises()
    expect(w.text()).toContain('ACME')
    expect(w.text()).toContain('BROUILLON')

    accountingService.issueCreditNote.mockResolvedValue({})
    await w.find('[data-test="issue-cn-cn1"]').trigger('click')
    await flushPromises()
    expect(accountingService.issueCreditNote).toHaveBeenCalledWith('cn1')
  })

  it('creates a credit note from a chosen issued invoice', async () => {
    accountingService.creditNotes.mockResolvedValue(emptyPage)
    accountingService.invoices.mockImplementation((params: any) =>
      Promise.resolve(params.status === 'issued'
        ? { data: [{ id: 'inv7', number: 'FA-000007', customer_name: 'Client SA', total_minor: 118000, status: 'issued' }], meta: { current_page: 1, last_page: 1, total: 1 } }
        : emptyPage))

    const w = mountView()
    await flushPromises()

    await w.find('[data-test="new-credit-note"]').trigger('click')
    await flushPromises()
    await w.find('[data-test="cn-source"]').setValue('inv7')

    accountingService.createCreditNote.mockResolvedValue({})
    await w.find('[data-test="cn-submit"]').trigger('click')
    await flushPromises()
    expect(accountingService.createCreditNote).toHaveBeenCalledWith('inv7')
  })

  it('surfaces a server error when application exceeds the balance', async () => {
    accountingService.creditNotes.mockResolvedValue({
      data: [{ id: 'cn2', number: 'AV-000001', customer_name: 'X', credit_note_of_id: 'inv2', issue_date: '2026-07-05', subtotal_minor: 50000, tax_total_minor: 0, total_minor: 50000, paid_minor: 0, status: 'issued', kind: 'credit_note', lines: [] }],
      meta: { current_page: 1, last_page: 1, total: 1 },
    })
    const w = mountView()
    await flushPromises()

    await w.find('[data-test="apply-cn2"]').trigger('click')
    await flushPromises()
    expect((w.find('[data-test="apply-invoice-id"]').element as HTMLInputElement).value).toBe('inv2')

    accountingService.applyCreditNote.mockRejectedValue({ response: { data: { message: 'Application supérieure au disponible.' } } })
    await w.find('[data-test="apply-submit"]').trigger('click')
    await flushPromises()
    expect(w.text()).toContain('Application supérieure au disponible.')
  })
})
