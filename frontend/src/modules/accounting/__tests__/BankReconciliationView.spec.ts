import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createWebHistory } from 'vue-router'
import BankReconciliationView from '@/modules/accounting/views/BankReconciliationView.vue'
import { setupManagerAuth } from '@/test-utils/setupAuth'
import { vFocusTrap } from '@/directives/focusTrap'

const { accountingService } = vi.hoisted(() => ({
  accountingService: { accounts: vi.fn(), bankReconciliation: vi.fn(), pointBankLines: vi.fn() },
}))
vi.mock('@/modules/accounting/services/accountingService', () => ({ accountingService }))

const router = createRouter({
  history: createWebHistory(),
  routes: ['invoices', 'balance', 'lettrage', 'bank-reconciliation', 'statements', 'chart'].map(p => ({ path: `/accounting/${p}`, component: { template: '<div/>' } })),
})

function mountView() {
  return mount(BankReconciliationView, {
    global: { plugins: [router, setupManagerAuth()], directives: { 'focus-trap': vFocusTrap }, stubs: { teleport: true } },
  })
}

const ACC = { data: [{ id: 'a521', code: '521', name: 'Banque', kind: 'asset' }, { id: 'a411', code: '411', name: 'Clients', kind: 'asset' }], meta: { current_page: 1, last_page: 1, total: 2 } }
const line = (id: string, d: number, c: number, pointed = false) => ({ id, date: '2026-01-01', number: 'BQ1', journal: 'BQ', label: 'op', debit_minor: d, credit_minor: c, pointed })

describe('BankReconciliationView', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    accountingService.accounts.mockResolvedValue(ACC)
    accountingService.bankReconciliation.mockResolvedValue({
      account: { id: 'a521', code: '521', name: 'Banque' },
      lines: [line('l1', 500000, 0), line('l2', 0, 100000)],
      summary: { book_balance_minor: 400000, pointed_balance_minor: 0, outstanding_debit_minor: 500000, outstanding_credit_minor: 100000 },
    })
  })

  it('lists only treasury (class 5) accounts and shows the book balance', async () => {
    const w = mountView()
    await flushPromises()
    // Le sélecteur ne propose que les comptes de classe 5.
    const options = w.find('[data-test="br-account"]').findAll('option').map(o => o.text())
    expect(options.some(o => o.includes('521'))).toBe(true)
    expect(options.some(o => o.includes('411'))).toBe(false)

    await w.find('[data-test="br-account"]').setValue('a521')
    await flushPromises()
    expect(accountingService.bankReconciliation).toHaveBeenCalledWith('a521', undefined)
    expect(w.find('[data-test="br-book"]').text().replace(/\s/g, '')).toContain('4000')
  })

  it('ticks a line to point it', async () => {
    const w = mountView()
    await flushPromises()
    await w.find('[data-test="br-account"]').setValue('a521')
    await flushPromises()

    accountingService.pointBankLines.mockResolvedValue({
      account: { id: 'a521', code: '521', name: 'Banque' },
      lines: [line('l1', 500000, 0, true), line('l2', 0, 100000)],
      summary: { book_balance_minor: 400000, pointed_balance_minor: 500000, outstanding_debit_minor: 0, outstanding_credit_minor: 100000 },
    })
    await w.find('[data-test="br-check-l1"]').trigger('change')
    await flushPromises()
    expect(accountingService.pointBankLines).toHaveBeenCalledWith('a521', ['l1'], true)
  })
})
