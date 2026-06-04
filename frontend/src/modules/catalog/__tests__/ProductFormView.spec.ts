import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import ProductFormView from '@/modules/catalog/views/ProductFormView.vue'
import { setupManagerAuth } from '@/test-utils/setupAuth'
import client from '@/api/client'

const router = createRouter({
  history: createMemoryHistory(),
  routes: [
    { path: '/catalog', name: 'catalog.products', component: { template: '<div/>' } },
    { path: '/catalog/products/create', name: 'catalog.products.create', component: { template: '<div/>' } },
    { path: '/catalog/products/:id', name: 'catalog.products.show', component: { template: '<div/>' } },
    { path: '/catalog/products/:id/edit', name: 'catalog.products.edit', component: { template: '<div/>' } },
  ],
})

async function mountCreate() {
  await router.push('/catalog/products/create')
  // categories.list() → /api/catalog/categories
  vi.mocked(client.get).mockResolvedValue({ data: { data: [] } })
  const w = mount(ProductFormView, { global: { plugins: [router, setupManagerAuth()] } })
  await flushPromises()
  return w
}

describe('ProductFormView (create mode)', () => {
  beforeEach(() => vi.clearAllMocks())

  it('renders the "Nouveau produit" form (not edit)', async () => {
    const w = await mountCreate()
    expect(w.text()).toContain('Nouveau produit')
    expect(w.find('input').exists()).toBe(true)
  })

  it('computes the cartesian combination count from variant axes', async () => {
    const w = await mountCreate()
    // Enable variants
    await w.find('.toggle-wrap input[type="checkbox"]').setValue(true)
    await flushPromises()

    // Axis 1 ("Taille" by default) — add two values via the value input
    const valueInputs = () => w.findAll('.axis-value-input')
    await valueInputs()[0].setValue('S')
    await valueInputs()[0].trigger('blur')
    await valueInputs()[0].setValue('M')
    await valueInputs()[0].trigger('blur')
    await flushPromises()

    // Add a second axis → it starts with an EMPTY name (excluded from the count
    // until named — a real guard in combinationCount). Name it, then add values.
    await w.find('.add-axis-btn').trigger('click')
    await flushPromises()
    const nameInputs = w.findAll('.axis-name-input')
    await nameInputs[1].setValue('Couleur')
    const inputs2 = w.findAll('.axis-value-input')
    await inputs2[1].setValue('Rouge')
    await inputs2[1].trigger('blur')
    await inputs2[1].setValue('Bleu')
    await inputs2[1].trigger('blur')
    await flushPromises()

    // 2 (Taille: S,M) × 2 (Couleur: Rouge,Bleu) = 4
    expect(w.find('.combo-count').text()).toContain('4')
  })

  it('submits create with price converted to centimes (× 100)', async () => {
    const w = await mountCreate()
    vi.mocked(client.post).mockResolvedValue({ data: { data: { id: 'new-prod' } } })

    // Fill name + price (4 200 → 420000 centimes)
    const nameInput = w.find('input[placeholder="Ex : Boubou bazin brodé"]')
    await nameInput.setValue('Bassine')
    const priceInput = w.find('input[type="number"]')
    await priceInput.setValue(4200)

    await w.find('form').trigger('submit')
    await flushPromises()

    expect(vi.mocked(client.post)).toHaveBeenCalled()
    const [url, payload] = vi.mocked(client.post).mock.calls[0]
    expect(url).toBe('/api/catalog/products')
    expect(payload).toMatchObject({ name: 'Bassine', price_amount: 420000 })
  })
})
