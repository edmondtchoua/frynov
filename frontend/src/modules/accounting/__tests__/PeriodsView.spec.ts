import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createWebHistory } from 'vue-router'
import PeriodsView from '@/modules/accounting/views/PeriodsView.vue'
import { setupManagerAuth } from '@/test-utils/setupAuth'
import { vFocusTrap } from '@/directives/focusTrap'

const { accountingService } = vi.hoisted(() => ({
  accountingService: { fiscalYears: vi.fn(), lockPeriod: vi.fn(), unlockPeriod: vi.fn(), closeFiscalYear: vi.fn() },
}))
vi.mock('@/modules/accounting/services/accountingService', () => ({ accountingService }))

const router = createRouter({
  history: createWebHistory(),
  routes: ['invoices', 'credit-notes', 'entries', 'balance', 'lettrage', 'chart', 'taxes', 'periods', 'settings'].map(p => ({ path: `/accounting/${p}`, component: { template: '<div/>' } })),
})

function mountView() {
  return mount(PeriodsView, {
    global: { plugins: [router, setupManagerAuth()], directives: { 'focus-trap': vFocusTrap }, stubs: { teleport: true } },
  })
}

describe('PeriodsView — clôture', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    accountingService.fiscalYears.mockResolvedValue([
      { id: 'fy26', label: '2026', status: 'open', periods: [{ id: 'p1', label: '2026-01', starts_on: '2026-01-01', ends_on: '2026-01-31', status: 'open' }] },
    ])
  })

  it('closes an open fiscal year and surfaces the result', async () => {
    const w = mountView()
    await flushPromises()

    await w.find('[data-test="close-fy-2026"]').trigger('click')
    await flushPromises()

    accountingService.closeFiscalYear.mockResolvedValue({
      fiscal_year: { id: 'fy26', label: '2026', status: 'closed' },
      next_year: { id: 'fy27', label: '2027', status: 'open' },
      carry_forward_entry: { number: 'OD26-000003' },
      result_minor: 300000,
    })
    await w.find('[data-test="close-submit"]').trigger('click')
    await flushPromises()

    expect(accountingService.closeFiscalYear).toHaveBeenCalledWith('fy26')
    const banner = w.find('[data-test="close-success"]')
    expect(banner.exists()).toBe(true)
    expect(banner.text()).toContain('2027')
    expect(banner.text()).toContain('OD26-000003')
  })

  it('surfaces a server error when the next year is closed', async () => {
    const w = mountView()
    await flushPromises()
    await w.find('[data-test="close-fy-2026"]').trigger('click')
    await flushPromises()

    accountingService.closeFiscalYear.mockRejectedValue({ response: { data: { message: "L'exercice suivant est clôturé." } } })
    await w.find('[data-test="close-submit"]').trigger('click')
    await flushPromises()
    expect(w.find('[data-test="close-error"]').text()).toContain('clôturé')
  })
})
