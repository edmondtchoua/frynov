import client from '@/api/client'
import type {
  Category,
  CreateCategoryPayload,
  CreateProductPayload,
  Product,
} from '../types'
import type { CreateVariantPayload, LabelBatchPayload, ProductVariantFull } from '../types'

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

  // ── Variants ───────────────────────────────────────────────────────────────

  createVariant(productId: string, data: CreateVariantPayload): Promise<ProductVariantFull> {
    return client.post(`/api/catalog/products/${productId}/variants`, data).then(r => r.data.data)
  },

  updateVariant(productId: string, variantId: string, data: Partial<CreateVariantPayload>): Promise<ProductVariantFull> {
    return client.put(`/api/catalog/products/${productId}/variants/${variantId}`, data).then(r => r.data.data)
  },

  deleteVariant(productId: string, variantId: string): Promise<void> {
    return client.delete(`/api/catalog/products/${productId}/variants/${variantId}`).then(() => undefined)
  },

  // ── Labels / Printing ──────────────────────────────────────────────────────

  /**
   * Fetch label HTML for a single product and open it in a new window for printing.
   * The backend returns a full HTML page with embedded CSS and barcodes.
   */
  getLabelUrl(productId: string, options: {
    copies?: number
    format?: 'thermal' | 'a4sheet'
    price?: boolean
    qr?: boolean
    variantId?: string
  } = {}): string {
    const params = new URLSearchParams()
    if (options.copies)  params.set('copies', String(options.copies))
    if (options.format)  params.set('format', options.format)
    if (options.price !== undefined) params.set('price', options.price ? '1' : '0')
    if (options.qr !== undefined)    params.set('qr', options.qr ? '1' : '0')

    const base = import.meta.env.VITE_API_BASE_URL ?? ''
    const token = localStorage.getItem('auth_token') ?? ''

    if (options.variantId) {
      return `${base}/api/catalog/products/${productId}/variants/${options.variantId}/label?${params}`
    }
    return `${base}/api/catalog/products/${productId}/label?${params}`
  },

  async printBatch(payload: LabelBatchPayload): Promise<void> {
    const token = localStorage.getItem('auth_token') ?? ''
    const resp = await client.post('/api/catalog/products/labels/batch', payload, {
      responseType: 'text',
    })
    const html  = resp.data as string
    const win   = window.open('', '_blank')
    if (win) {
      win.document.write(html)
      win.document.close()
      win.onload = () => win.print()
    }
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
