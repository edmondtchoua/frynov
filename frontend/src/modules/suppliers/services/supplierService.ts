import client from '@/api/client'
import type { CreateSupplierPayload, Supplier, UpdateSupplierPayload } from '../types'

interface PaginatedSuppliers {
  data: Supplier[]
  meta: { current_page: number; last_page: number; per_page: number; total: number }
}

export const supplierService = {
  list(params?: { page?: number; per_page?: number; search?: string; status?: string }): Promise<PaginatedSuppliers> {
    return client.get('/api/suppliers', { params }).then(r => r.data)
  },

  get(id: string): Promise<{ data: Supplier }> {
    return client.get(`/api/suppliers/${id}`).then(r => r.data)
  },

  search(q: string): Promise<{ data: Supplier[] }> {
    return client.get('/api/suppliers/search', { params: { q } }).then(r => r.data)
  },

  create(payload: CreateSupplierPayload): Promise<{ data: Supplier }> {
    return client.post('/api/suppliers', payload).then(r => r.data)
  },

  update(id: string, payload: UpdateSupplierPayload): Promise<{ data: Supplier }> {
    return client.put(`/api/suppliers/${id}`, payload).then(r => r.data)
  },

  delete(id: string): Promise<void> {
    return client.delete(`/api/suppliers/${id}`)
  },
}
