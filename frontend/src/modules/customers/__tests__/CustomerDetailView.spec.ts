import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import CustomerDetailView from '@/modules/customers/views/CustomerDetailView.vue'
import { setupManagerAuth } from '@/test-utils/setupAuth'
import client from '@/api/client'

const router = createRouter({
  history: createMemoryHistory(),
  routes: [
    { path: '/customers', name: 'customers.list', component: { template: '<div/>' } },
    { path: '/customers/:id', name: 'customers.show', component: { template: '<div/>' } },
    { path: '/orders/new', name: 'orders.create', component: { template: '<div/>' } },
    { path: '/orders/:id', name: 'orders.show', component: { template: '<div/>' } },
  ],
})

const CUSTOMER = {
  id: 'cust-1', name: 'Aminata Diallo', email: 'aminata@mail.sn', phone: '+221 77 000 00 00',
  orders_count: 2, address: null, notes: null,
}
const ORDERS = [
  { id: 'o1', number: 'ORD-00001', status: 'fulfilled', total_amount: 3800000, currency: 'XOF', created_at: '2026-05-10T10:00:00Z' },
]

function mockGet() {
  vi.mocked(client.get).mockImplementation((url: string) => {
    if (url.endsWith('/orders')) return Promise.resolve({ data: { data: ORDERS } }) as any
    return Promise.resolve({ data: { data: CUSTOMER } }) as any // customerService.get → r.data.data
  })
}

async function mountView() {
  await router.push('/customers/cust-1')
  mockGet()
  const w = mount(CustomerDetailView, { global: { plugins: [router, setupManagerAuth()] } })
  await flushPromises()
  return w
}

describe('CustomerDetailView', () => {
  beforeEach(() => vi.clearAllMocks())

  it('renders the customer name and contact', async () => {
    const w = await mountView()
    expect(w.text()).toContain('Aminata Diallo')
    expect(w.text()).toContain('aminata@mail.sn')
  })

  it('shows the order count from loadCount', async () => {
    const w = await mountView()
    expect(w.text()).toContain('2')   // orders_count
  })

  it('lists orders with the total formatted in major units (÷100)', async () => {
    const w = await mountView()
    expect(w.text()).toContain('ORD-00001')
    // 3 800 000 centimes → "38 000 XOF", NOT "3 800 000"
    expect(w.text()).toContain('38')
    expect(w.text()).not.toContain('3 800 000')
  })
})
