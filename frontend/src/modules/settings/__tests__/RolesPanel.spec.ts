import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import RolesPanel from '@/modules/settings/components/RolesPanel.vue'

vi.mock('@/api/client', () => ({ default: { get: vi.fn(), post: vi.fn(), patch: vi.fn(), delete: vi.fn() } }))
import client from '@/api/client'
import { vFocusTrap } from '@/directives/focusTrap'

const ROLES = {
  data: [
    { id: 1, name: 'admin', is_custom: false, permissions: ['products.create'] },
    { id: 2, name: 'Resp dépôt', is_custom: true, permissions: ['inventory.adjust', 'orders.manage'] },
  ],
  grantable: ['catalog.view', 'products.create', 'inventory.adjust', 'orders.manage'],
}

async function mountPanel() {
  vi.mocked(client.get).mockResolvedValue({ data: ROLES } as any)
  const w = mount(RolesPanel, {
    global: {
      directives: { 'focus-trap': vFocusTrap },  // modal now uses BaseModal (v-focus-trap)
      stubs: { teleport: true },                 // render BaseModal's Teleport inline
    },
  })
  await flushPromises()
  return w
}

describe('RolesPanel', () => {
  beforeEach(() => { vi.clearAllMocks() })

  it('lists base (read-only) and custom roles from the API', async () => {
    const w = await mountPanel()
    expect(vi.mocked(client.get)).toHaveBeenCalledWith('/api/workspace/roles')
    expect(w.text()).toContain('Resp dépôt')
    expect(w.text()).toContain('Personnalisé')
    expect(w.text()).toContain('Base')
  })

  it('opens the create modal with grantable permissions grouped by module', async () => {
    const w = await mountPanel()
    await w.find('.btn-sm-pad').trigger('click')
    await flushPromises()
    expect(w.text()).toContain('Nouveau rôle personnalisé')
    expect(w.text()).toContain('Catalogue') // from catalog.view
    expect(w.text()).toContain('Produits')  // from products.create
  })

  it('creates a custom role through the service', async () => {
    const w = await mountPanel()
    vi.mocked(client.post).mockResolvedValue({ data: { id: 3, name: 'New role', is_custom: true, permissions: [] } } as any)

    await w.find('.btn-sm-pad').trigger('click')
    await w.find('.modal input.form-input').setValue('New role')
    await w.find('.btn-submit').trigger('click')
    await flushPromises()

    expect(vi.mocked(client.post)).toHaveBeenCalledWith(
      '/api/workspace/roles',
      expect.objectContaining({ name: 'New role' }),
    )
  })
})
