import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createWebHistory } from 'vue-router'
import BalanceView from '@/modules/accounting/views/BalanceView.vue'
import { setupManagerAuth } from '@/test-utils/setupAuth'
import { vFocusTrap } from '@/directives/focusTrap'

const { accountingService } = vi.hoisted(() => ({
  accountingService: { trialBalance: vi.fn(), generalLedger: vi.fn() },
}))
vi.mock('@/modules/accounting/services/accountingService', () => ({ accountingService }))

const router = createRouter({
  history: createWebHistory(),
  routes: ['invoices', 'credit-notes', 'entries', 'balance', 'chart', 'taxes', 'periods', 'settings'].map(p => ({ path: `/accounting/${p}`, component: { template: '<div/>' } })),
})

function mountView() {
  return mount(BalanceView, {
    global: { plugins: [router, setupManagerAuth()], directives: { 'focus-trap': vFocusTrap }, stubs: { teleport: true } },
  })
}

describe('BalanceView', () => {
  beforeEach(() => vi.clearAllMocks())

  it('renders the trial balance rows and balanced totals', async () => {
    accountingService.trialBalance.mockResolvedValue({
      rows: [
        { account_id: 'a1', code: '571', name: 'Caisse', kind: 'asset', opening_minor: 0, debit_minor: 100000, credit_minor: 0, closing_minor: 100000 },
        { account_id: 'a2', code: '701', name: 'Ventes', kind: 'revenue', opening_minor: 0, debit_minor: 0, credit_minor: 100000, closing_minor: -100000 },
      ],
      totals: { debit_minor: 100000, credit_minor: 100000, closing_debit_minor: 100000, closing_credit_minor: 100000 },
      from: null, to: '2026-12-31',
    })
    const w = mountView()
    await flushPromises()

    expect(w.find('[data-test="bal-row-571"]').text()).toContain('Caisse')
    expect(w.find('[data-test="bal-row-701"]').text()).toContain('Ventes')
    expect(w.find('[data-test="bal-total-debit"]').text().replace(/\s/g, '')).toContain('1000')
    // équilibre : ✓ affiché
    expect(w.text()).toContain('✓')
  })

  it('drills into an account and loads its general ledger', async () => {
    accountingService.trialBalance.mockResolvedValue({
      rows: [{ account_id: 'a2', code: '701', name: 'Ventes', kind: 'revenue', opening_minor: 0, debit_minor: 0, credit_minor: 160000, closing_minor: -160000 }],
      totals: { debit_minor: 160000, credit_minor: 160000, closing_debit_minor: 160000, closing_credit_minor: 160000 },
      from: null, to: '2026-12-31',
    })
    accountingService.generalLedger.mockResolvedValue({
      account: { id: 'a2', code: '701', name: 'Ventes', kind: 'revenue' },
      opening_minor: 0, debit_total_minor: 0, credit_total_minor: 160000, closing_minor: -160000,
      lines: [
        { date: '2026-01-01', number: 'VT26-000001', journal: 'VT', label: 'Vente', debit_minor: 0, credit_minor: 100000, running_minor: -100000 },
        { date: '2026-04-01', number: 'VT26-000002', journal: 'VT', label: 'Vente', debit_minor: 0, credit_minor: 60000, running_minor: -160000 },
      ],
    })
    const w = mountView()
    await flushPromises()

    await w.find('[data-test="bal-row-701"]').trigger('click')
    await flushPromises()
    expect(accountingService.generalLedger).toHaveBeenCalledWith('a2', expect.any(Object))
    expect(w.findAll('[data-test="ledger-line"]')).toHaveLength(2)
    expect(w.text()).toContain('VT26-000002')
  })
})
