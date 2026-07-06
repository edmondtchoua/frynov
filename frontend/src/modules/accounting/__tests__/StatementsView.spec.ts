import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createWebHistory } from 'vue-router'
import StatementsView from '@/modules/accounting/views/StatementsView.vue'
import { setupManagerAuth } from '@/test-utils/setupAuth'
import { vFocusTrap } from '@/directives/focusTrap'

const { accountingService } = vi.hoisted(() => ({
  accountingService: { balanceSheet: vi.fn(), incomeStatement: vi.fn() },
}))
vi.mock('@/modules/accounting/services/accountingService', () => ({ accountingService }))

const router = createRouter({
  history: createWebHistory(),
  routes: ['invoices', 'credit-notes', 'entries', 'balance', 'lettrage', 'statements', 'chart', 'taxes', 'periods', 'settings'].map(p => ({ path: `/accounting/${p}`, component: { template: '<div/>' } })),
})

function mountView() {
  return mount(StatementsView, {
    global: { plugins: [router, setupManagerAuth()], directives: { 'focus-trap': vFocusTrap }, stubs: { teleport: true } },
  })
}

describe('StatementsView', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    accountingService.balanceSheet.mockResolvedValue({
      actif: [{ code: '571', name: 'Caisse', class: 5, amount_minor: 600000 }],
      passif: [{ code: '401', name: 'Fournisseurs', class: 4, amount_minor: 150000 }, { code: '13', name: 'Résultat', class: 1, amount_minor: 450000 }],
      total_actif_minor: 600000, total_passif_minor: 600000, result_minor: 450000, balanced: true, from: null, to: '2026-12-31',
    })
    accountingService.incomeStatement.mockResolvedValue({
      charges: [{ code: '601', name: 'Achats', class: 6, amount_minor: 150000 }],
      produits: [{ code: '701', name: 'Ventes', class: 7, amount_minor: 600000 }],
      total_charges_minor: 150000, total_produits_minor: 600000, result_minor: 450000, from: null, to: '2026-12-31',
    })
  })

  it('renders the balance sheet balanced by default', async () => {
    const w = mountView()
    await flushPromises()
    expect(accountingService.balanceSheet).toHaveBeenCalled()
    expect(w.find('[data-test="bs-total-actif"]').text().replace(/\s/g, '')).toContain('6000')
    expect(w.find('[data-test="bs-total-passif"]').text().replace(/\s/g, '')).toContain('6000')
    expect(w.find('[data-test="bs-balanced"]').text()).toContain('équilibré')
  })

  it('switches to the income statement and shows the profit', async () => {
    const w = mountView()
    await flushPromises()

    await w.find('[data-test="tab-resultat"]').trigger('click')
    await flushPromises()
    expect(accountingService.incomeStatement).toHaveBeenCalled()
    const banner = w.find('[data-test="is-result"]')
    expect(banner.text()).toContain('bénéfice')
    expect(banner.text().replace(/\s/g, '')).toContain('4500')
  })
})
