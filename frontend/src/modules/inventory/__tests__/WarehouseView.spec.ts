import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import WarehouseView from '@/modules/inventory/views/WarehouseView.vue'
import { setupManagerAuth } from '@/test-utils/setupAuth'
import { vFocusTrap } from '@/directives/focusTrap'
import client from '@/api/client'

const WAREHOUSES = {
  data: [{
    id: 'w1', name: 'Entrepôt Dakar', code: 'WH-DKR', type: 'warehouse', currency: 'XOF',
    is_active: true, is_default: true, sells_online: false, address: { city: 'Dakar', country: 'SN' },
  }],
}

async function mountView() {
  vi.mocked(client.get).mockResolvedValue({ data: WAREHOUSES } as any)
  const w = mount(WarehouseView, {
    global: {
      plugins: [setupManagerAuth()],
      directives: { 'focus-trap': vFocusTrap },   // BaseModal uses v-focus-trap
      stubs: { teleport: true, InventoryTabNav: true },
    },
  })
  await flushPromises()
  return w
}

describe('WarehouseView', () => {
  beforeEach(() => vi.clearAllMocks())

  it('lists warehouses with name and code', async () => {
    const w = await mountView()
    expect(w.text()).toContain('Entrepôt Dakar')
    expect(w.text()).toContain('WH-DKR')
  })

  it('opens the create form in a shared BaseModal dialog (UX-03)', async () => {
    const w = await mountView()
    expect(w.find('[role="dialog"]').exists()).toBe(false)

    await w.findAll('button').find(b => b.text().includes('Nouvel emplacement'))!.trigger('click')
    await flushPromises()

    const dialog = w.find('[role="dialog"]')
    expect(dialog.exists()).toBe(true)
    expect(dialog.attributes('aria-modal')).toBe('true')
  })
})
