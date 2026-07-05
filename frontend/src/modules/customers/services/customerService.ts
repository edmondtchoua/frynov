import client from '@/api/client'
import type { CreateCustomerPayload, Customer } from '../types'

interface PaginatedCustomers {
  data: Customer[]
  meta: { current_page: number; last_page: number; per_page: number; total: number }
}

export const customerService = {
  list(params?: { search?: string; per_page?: number; page?: number }): Promise<PaginatedCustomers> {
    return client.get('/api/customers', { params }).then(r => r.data)
  },

  get(id: string): Promise<Customer> {
    return client.get(`/api/customers/${id}`).then(r => r.data.data)
  },

  create(data: CreateCustomerPayload): Promise<Customer> {
    return client.post('/api/customers', data).then(r => r.data.data)
  },

  update(id: string, data: Partial<CreateCustomerPayload>): Promise<Customer> {
    return client.put(`/api/customers/${id}`, data).then(r => r.data.data)
  },

  delete(id: string): Promise<void> {
    return client.delete(`/api/customers/${id}`)
  },

  search(q: string): Promise<Customer[]> {
    return client.get('/api/customers/search', { params: { q } }).then(r => r.data.data)
  },

  orders(id: string): Promise<any[]> {
    return client.get(`/api/customers/${id}/orders`).then(r => r.data.data ?? [])
  },
}
