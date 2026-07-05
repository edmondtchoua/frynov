import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import AppLayout from '@/layouts/AppLayout.vue'
import { setupManagerAuth, MANAGER_USER } from '@/test-utils/setupAuth'
import { useAuthStore } from '@/stores/auth'

// Heavy/async deps stubbed so we can mount the layout in isolation.
vi.mock('@/composables/useNotifications', () => ({
  useNotifications: () => ({ unreadCount: { value: 0 } }),
}))
vi.mock('@/api/client', () => ({
  default: { get: vi.fn().mockResolvedValue({ data: {} }), post: vi.fn(), patch: vi.fn(), delete: vi.fn() },
}))
const routerStub = { push: vi.fn() }
vi.mock('vue-router', async (orig) => {
  const actual = await (orig() as Promise<Record<string, unknown>>)
  return { ...actual, useRoute: () => ({ path: '/dashboard', name: 'dashboard' }), useRouter: () => routerStub }
})

function mountLayout(activeModules: string[] | undefined, roles: string[] = ['manager']) {
  const pinia = setupManagerAuth()
  useAuthStore().user = { ...MANAGER_USER, roles, active_modules: activeModules } as any
  return mount(AppLayout, {
    global: {
      plugins: [pinia],
      stubs: {
        RouterLink: { template: '<a class="rl"><slot /></a>' },
        NotificationCenter: true,
        FrynovLogo: true,
      },
    },
  })
}

describe('AppLayout — module/permission-driven navigation (UX-01)', () => {
  it('locks nav entries whose module is not active', () => {
    const w = mountLayout(['dashboard', 'catalog', 'orders'])

    const lockedLabels = w.findAll('.nav-item--locked').map(b => b.text())
    // inventory/customers are not in active_modules → locked
    expect(lockedLabels.join(' ')).toContain('Stock & Inventaire')
    expect(lockedLabels.join(' ')).toContain('Clients')
    // catalog IS active → not locked
    expect(lockedLabels.join(' ')).not.toContain('Catalogue')
  })

  it('shows everything (no locks) when active modules are unknown', () => {
    const w = mountLayout([]) // empty → module info unknown → never lock
    expect(w.findAll('.nav-item--locked')).toHaveLength(0)
  })

  it('hides manager-only entries for a restricted role', () => {
    const w = mountLayout(['dashboard', 'catalog'], ['agent'])
    // suppliers/reports/import/marketplace are managerOnly → absent entirely
    expect(w.text()).not.toContain('Fournisseurs')
    expect(w.text()).not.toContain('Rapports')
  })

  it('exposes accessible sidebar controls', () => {
    const w = mountLayout(['dashboard'])
    expect(w.find('nav.sidebar-nav').attributes('aria-label')).toBe('Navigation principale')
    expect(w.find('.collapse-btn').attributes('aria-expanded')).toBeDefined()
  })
})
