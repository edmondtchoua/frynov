import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createWebHistory } from 'vue-router'
import ChartOfAccountsView from '@/modules/accounting/views/ChartOfAccountsView.vue'
import { setupManagerAuth } from '@/test-utils/setupAuth'
import { vFocusTrap } from '@/directives/focusTrap'

const { accountingService } = vi.hoisted(() => ({
  accountingService: {
    overview: vi.fn(), provision: vi.fn(), accounts: vi.fn(), createAccount: vi.fn(),
    updateAccount: vi.fn(), journals: vi.fn(), taxes: vi.fn(), createTax: vi.fn(),
    updateTax: vi.fn(), settings: vi.fn(), updateSettings: vi.fn(), fiscalYears: vi.fn(),
    lockPeriod: vi.fn(), unlockPeriod: vi.fn(),
  },
}))
vi.mock('@/modules/accounting/services/accountingService', () => ({ accountingService }))

const router = createRouter({
  history: createWebHistory(),
  routes: ['chart', 'taxes', 'periods', 'settings'].map(p => ({ path: `/accounting/${p}`, component: { template: '<div/>' } })),
})

const CLASSES = Array.from({ length: 9 }, (_, i) => ({ code: i + 1, name: `Classe ${i + 1}`, type: i < 5 ? 'bilan' : 'gestion' }))

function mountView() {
  return mount(ChartOfAccountsView, {
    global: { plugins: [router, setupManagerAuth()], directives: { 'focus-trap': vFocusTrap }, stubs: { teleport: true } },
  })
}

describe('ChartOfAccountsView', () => {
  beforeEach(() => vi.clearAllMocks())

  it('offers the provisioning wizard when the referential is not initialised', async () => {
    accountingService.overview.mockResolvedValue({ provisioned: false, accounts: 0, journals: 0, taxes: 0, classes: CLASSES })
    const w = mountView()
    await flushPromises()

    expect(w.find('[data-test="provision-box"]').exists()).toBe(true)

    accountingService.provision.mockResolvedValue({})
    accountingService.accounts.mockResolvedValue({ data: [], meta: { current_page: 1, last_page: 1, total: 0 } })
    await w.find('[data-test="provision"]').trigger('click')
    await flushPromises()

    expect(accountingService.provision).toHaveBeenCalledTimes(1)
    expect(w.find('[data-test="provision-box"]').exists()).toBe(false)
  })

  it('lists the chart of accounts with class filter once provisioned', async () => {
    accountingService.overview.mockResolvedValue({ provisioned: true, accounts: 2, journals: 8, taxes: 1, classes: CLASSES })
    accountingService.accounts.mockResolvedValue({
      data: [
        { id: 'a1', class_code: 5, code: '571', name: 'Caisse', parent_id: null, kind: 'asset', is_auxiliary: false, is_system: true, is_active: true },
        { id: 'a2', class_code: 7, code: '701', name: 'Ventes de marchandises', parent_id: null, kind: 'revenue', is_auxiliary: false, is_system: true, is_active: true },
      ],
      meta: { current_page: 1, last_page: 1, total: 2 },
    })
    const w = mountView()
    await flushPromises()

    const text = w.text()
    expect(text).toContain('571')
    expect(text).toContain('Caisse')
    expect(text).toContain('Ventes de marchandises')

    // Filtre de classe → rechargement avec le paramètre.
    await w.find('[data-test="class-filter"]').setValue('7')
    await flushPromises()
    expect(accountingService.accounts).toHaveBeenLastCalledWith(expect.objectContaining({ class: 7 }))
  })

  it('creates an account from the modal and surfaces server errors', async () => {
    accountingService.overview.mockResolvedValue({ provisioned: true, accounts: 0, journals: 8, taxes: 1, classes: CLASSES })
    accountingService.accounts.mockResolvedValue({ data: [], meta: { current_page: 1, last_page: 1, total: 0 } })
    const w = mountView()
    await flushPromises()

    await w.find('[data-test="add-account"]').trigger('click')
    await w.find('[data-test="new-code"]').setValue('7011')
    await w.find('[data-test="new-name"]').setValue('Ventes Dakar')

    accountingService.createAccount.mockRejectedValue({ response: { data: { message: 'Code déjà utilisé.' } } })
    await w.find('[data-test="create-submit"]').trigger('click')
    await flushPromises()
    expect(w.find('[data-test="create-error"]').text()).toContain('Code déjà utilisé.')

    accountingService.createAccount.mockResolvedValue({})
    await w.find('[data-test="create-submit"]').trigger('click')
    await flushPromises()
    expect(accountingService.createAccount).toHaveBeenLastCalledWith({ code: '7011', name: 'Ventes Dakar', kind: 'revenue' })
  })
})
