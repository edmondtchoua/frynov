import client from '@/api/client'
import type {
  Category,
  CreateCategoryPayload,
  CreateProductPayload,
  Product,
} from '../types'

interface PaginatedProducts {
  data: Product[]
  meta: { current_page: number; last_page: number; per_page: number; total: number }
  links: { next: string | null; prev: string | null }
}

export const productService = {
  // ── Products ───────────────────────────────────────────────────────────────

  list(params?: {
    status?: string
    category_id?: string
    search?: string
    per_page?: number
    page?: number
  }): Promise<PaginatedProducts> {
    return client.get('/api/catalog/products', { params }).then(r => r.data)
  },

  get(id: string): Promise<Product> {
    return client.get(`/api/catalog/products/${id}`).then(r => r.data.data)
  },

  getBySku(sku: string): Promise<Product> {
    return client.get(`/api/catalog/products/sku/${sku}`).then(r => r.data.data)
  },

  create(data: CreateProductPayload): Promise<Product> {
    return client.post('/api/catalog/products', data).then(r => r.data.data)
  },

  update(id: string, data: Partial<CreateProductPayload>): Promise<Product> {
    return client.put(`/api/catalog/products/${id}`, data).then(r => r.data.data)
  },

  archive(id: string): Promise<void> {
    return client.patch(`/api/catalog/products/${id}/archive`).then(r => r.data)
  },

  activate(id: string): Promise<Product> {
    return client.patch(`/api/catalog/products/${id}/activate`).then(r => r.data.data)
  },

  // ── Categories ─────────────────────────────────────────────────────────────

  categories: {
    list(): Promise<Category[]> {
      return client.get('/api/catalog/categories').then(r => r.data.data)
    },

    create(data: CreateCategoryPayload): Promise<Category> {
      return client.post('/api/catalog/categories', data).then(r => r.data.data)
    },

    update(id: string, data: Partial<CreateCategoryPayload>): Promise<Category> {
      return client.put(`/api/catalog/categories/${id}`, data).then(r => r.data.data)
    },

    delete(id: string): Promise<void> {
      return client.delete(`/api/catalog/categories/${id}`)
    },
  },
}
