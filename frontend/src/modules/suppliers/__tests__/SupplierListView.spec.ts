import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import SupplierListView from '@/modules/suppliers/views/SupplierListView.vue'
import { setupManagerAuth } from '@/test-utils/setupAuth'
import { vFocusTrap } from '@/directives/focusTrap'
import client from '@/api/client'

const router = createRouter({
  history: createMemoryHistory(),
  routes: [
    { path: '/suppliers', name: 'suppliers.list', component: { template: '<div/>' } },
    { path: '/suppliers/:id', name: 'suppliers.show', component: { template: '<div/>' } },
  ],
})

// supplierService.list → res.data (rows) + res.meta
const LIST = {
  data: [{
    id: 'sup-1', code: 'SUP-001', name: 'Grossiste Dakar', status: 'active',
    contact_name: 'Awa Ndiaye', email: 'awa@grossiste.sn', phone: '+221 77 000 00 00',
    payment_terms: '30 jours', notes: null,
  }],
  meta: { current_page: 1, last_page: 1, per_page: 20, total: 1 },
}

async function mountView() {
  vi.mocked(client.get).mockResolvedValue({ data: LIST } as any)
  const w = mount(SupplierListView, {
    global: {
      plugins: [router, setupManagerAuth()],
      directives: { 'focus-trap': vFocusTrap },  // BaseModal uses v-focus-trap
      stubs: { teleport: true },                 // render BaseModal's Teleport inline
    },
  })
  await flushPromises()
  return w
}

describe('SupplierListView', () => {
  beforeEach(() => vi.clearAllMocks())

  it('renders suppliers with code and name', async () => {
    const w = await mountView()
    expect(w.text()).toContain('Grossiste Dakar')
    expect(w.text()).toContain('SUP-001')
  })

  it('opens the create form in a shared BaseModal dialog (UX-03)', async () => {
    const w = await mountView()
    expect(w.find('[role="dialog"]').exists()).toBe(false)

    await w.findAll('button').find(b => b.text().includes('Nouveau fournisseur'))!.trigger('click')
    await flushPromises()

    const dialog = w.find('[role="dialog"]')
    expect(dialog.exists()).toBe(true)
    expect(dialog.attributes('aria-modal')).toBe('true')
    expect(dialog.text()).toContain('Nouveau fournisseur')

    // footer submit button stays linked to the in-dialog <form> via the form attribute
    const submit = w.find('button[form="supplier-form"]')
    expect(submit.exists()).toBe(true)
    expect(submit.attributes('type')).toBe('submit')
  })
})
