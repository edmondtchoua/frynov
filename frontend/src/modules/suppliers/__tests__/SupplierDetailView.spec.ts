import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import SupplierDetailView from '@/modules/suppliers/views/SupplierDetailView.vue'
import { setupManagerAuth } from '@/test-utils/setupAuth'
import { setLocale } from '@/i18n'
import client from '@/api/client'

const router = createRouter({
  history: createMemoryHistory(),
  routes: [
    { path: '/suppliers', name: 'suppliers.list', component: { template: '<div/>' } },
    { path: '/suppliers/:id', name: 'suppliers.show', component: { template: '<div/>' } },
  ],
})

const SUPPLIER = {
  id: 'sup-1', code: 'SUP-001', name: 'Grossiste Dakar', status: 'active',
  contact_name: 'Awa Ndiaye', email: 'awa@grossiste.sn', phone: '+221 77 123 45 67',
  payment_terms: '30 jours', notes: null, address: null,
  created_at: '2026-05-01T10:00:00Z', updated_at: '2026-05-20T10:00:00Z',
}

async function mountView() {
  await router.push('/suppliers/sup-1')
  // supplierService.get → r.data === { data: supplier }
  vi.mocked(client.get).mockResolvedValue({ data: { data: SUPPLIER } })
  const w = mount(SupplierDetailView, { global: { plugins: [router, setupManagerAuth()] } })
  await flushPromises()
  return w
}

describe('SupplierDetailView', () => {
  beforeEach(() => { vi.clearAllMocks(); setLocale('fr') })  // assert against French labels

  it('renders the supplier name, code and contact info', async () => {
    const w = await mountView()
    expect(w.text()).toContain('Grossiste Dakar')
    expect(w.text()).toContain('SUP-001')
    expect(w.text()).toContain('Awa Ndiaye')
    expect(w.text()).toContain('awa@grossiste.sn')
  })

  it('shows an active status badge', async () => {
    const w = await mountView()
    expect(w.text()).toContain('Actif')
  })

  it('switches to edit mode and pre-fills the form', async () => {
    const w = await mountView()
    const editBtn = w.findAll('button').find(b => b.text().includes('Modifier'))!
    await editBtn.trigger('click')
    await flushPromises()
    const nameInput = w.find('input[required]')
    expect((nameInput.element as HTMLInputElement).value).toBe('Grossiste Dakar')
  })

  it('opens the delete confirmation modal', async () => {
    const w = await mountView()
    const delBtn = w.findAll('button').find(b => b.text().includes('Supprimer'))!
    await delBtn.trigger('click')
    await flushPromises()
    expect(w.text()).toContain('Supprimer ce fournisseur ?')
  })
})
