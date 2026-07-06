import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createWebHistory } from 'vue-router'
import LettrageView from '@/modules/accounting/views/LettrageView.vue'
import { setupManagerAuth } from '@/test-utils/setupAuth'
import { vFocusTrap } from '@/directives/focusTrap'

const { accountingService } = vi.hoisted(() => ({
  accountingService: { accounts: vi.fn(), lettrage: vi.fn(), letterLines: vi.fn(), unletterCode: vi.fn() },
}))
vi.mock('@/modules/accounting/services/accountingService', () => ({ accountingService }))

const router = createRouter({
  history: createWebHistory(),
  routes: ['invoices', 'credit-notes', 'entries', 'balance', 'lettrage', 'chart', 'taxes', 'periods', 'settings'].map(p => ({ path: `/accounting/${p}`, component: { template: '<div/>' } })),
})

function mountView() {
  return mount(LettrageView, {
    global: { plugins: [router, setupManagerAuth()], directives: { 'focus-trap': vFocusTrap }, stubs: { teleport: true } },
  })
}

const ACC = { data: [{ id: 'a411', code: '411', name: 'Clients', kind: 'asset' }], meta: { current_page: 1, last_page: 1, total: 1 } }
const line = (id: string, d: number, c: number, code: string | null = null) => ({ id, date: '2026-01-01', number: 'X', journal: 'OD', label: 'l', debit_minor: d, credit_minor: c, lettrage_code: code })

describe('LettrageView', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    accountingService.accounts.mockResolvedValue(ACC)
  })

  it('letters a balanced selection of two lines', async () => {
    accountingService.lettrage.mockResolvedValue({
      account: { id: 'a411', code: '411', name: 'Clients' },
      lines: [line('l1', 100000, 0), line('l2', 0, 100000)],
      summary: { lettered_debit_minor: 0, lettered_credit_minor: 0, open_debit_minor: 100000, open_credit_minor: 100000, open_balance_minor: 0 },
    })
    const w = mountView()
    await flushPromises()

    await w.find('[data-test="let-account"]').setValue('a411')
    await flushPromises()
    await w.find('[data-test="let-check-l1"]').setValue(true)
    await w.find('[data-test="let-check-l2"]').setValue(true)
    await flushPromises()

    // Bouton actif car la sélection est équilibrée (100 000 D = 100 000 C).
    const submit = w.find('[data-test="let-submit"]')
    expect((submit.element as HTMLButtonElement).disabled).toBe(false)

    accountingService.letterLines.mockResolvedValue({ code: 'A', account: { account: { id: 'a411', code: '411', name: 'Clients' }, lines: [], summary: { lettered_debit_minor: 100000, lettered_credit_minor: 100000, open_debit_minor: 0, open_credit_minor: 0, open_balance_minor: 0 } } })
    await submit.trigger('click')
    await flushPromises()
    expect(accountingService.letterLines).toHaveBeenCalledWith('a411', ['l1', 'l2'])
  })

  it('unletters a lettered group', async () => {
    accountingService.lettrage.mockResolvedValue({
      account: { id: 'a411', code: '411', name: 'Clients' },
      lines: [line('l1', 100000, 0, 'A'), line('l2', 0, 100000, 'A')],
      summary: { lettered_debit_minor: 100000, lettered_credit_minor: 100000, open_debit_minor: 0, open_credit_minor: 0, open_balance_minor: 0 },
    })
    const w = mountView()
    await flushPromises()
    await w.find('[data-test="let-account"]').setValue('a411')
    await flushPromises()

    accountingService.unletterCode.mockResolvedValue({ account: { account: { id: 'a411', code: '411', name: 'Clients' }, lines: [], summary: { lettered_debit_minor: 0, lettered_credit_minor: 0, open_debit_minor: 100000, open_credit_minor: 100000, open_balance_minor: 0 } } })
    await w.find('[data-test="unletter-A"]').trigger('click')
    await flushPromises()
    expect(accountingService.unletterCode).toHaveBeenCalledWith('a411', 'A')
  })
})
