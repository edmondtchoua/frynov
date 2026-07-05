import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import AdminLayout from '@/layouts/AdminLayout.vue'
import { setupManagerAuth } from '@/test-utils/setupAuth'

vi.mock('vue-router', async (orig) => {
  const actual = await (orig() as Promise<Record<string, unknown>>)
  return { ...actual, useRoute: () => ({ name: 'admin.dashboard' }), useRouter: () => ({ push: vi.fn() }) }
})

function mountAdmin() {
  return mount(AdminLayout, {
    global: {
      plugins: [setupManagerAuth()],
      stubs: { RouterLink: { template: '<a><slot /></a>' }, RouterView: true },
    },
  })
}

describe('AdminLayout — admin/tenant consistency (UX-02)', () => {
  it('uses French labels (Espaces clients, not Tenants)', () => {
    const w = mountAdmin()
    expect(w.text()).toContain('Espaces clients')
    expect(w.text()).not.toContain('Tenants')
  })

  it('does not expose a link back to a tenant route forbidden to super-admins', () => {
    const w = mountAdmin()
    expect(w.text()).not.toContain("Retour à l'app")
  })

  it('exposes an accessible back-office nav landmark', () => {
    const w = mountAdmin()
    expect(w.find('nav.admin-nav').attributes('aria-label')).toBe('Navigation back-office')
  })
})
