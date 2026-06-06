import client from '@/api/client'
import type {
  Category,
  CreateCategoryPayload,
  CreateProductPayload,
  Product,
  ProductAttribute,
  ProductStockSummary,
  StockMovementItem,
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

  /** Full detail with supplier + attributes — for ProductShowPage */
  getDetail(id: string): Promise<Product> {
    return client.get(`/api/catalog/products/${id}`, { params: { detail: 1 } }).then(r => r.data.data)
  },

  /** Aggregated stock across warehouses */
  getStockSummary(id: string): Promise<ProductStockSummary> {
    return client.get(`/api/catalog/products/${id}/stock-summary`).then(r => r.data)
  },

  /** Create initial stock movement after product creation */
  createInitialStock(id: string, data: {
    quantity: number
    warehouse_id?: string
    unit_cost_cents?: number
    note?: string
    variant_id?: string
  }): Promise<{ message: string; movement: any; stock: any }> {
    return client.post(`/api/catalog/products/${id}/initial-stock`, data).then(r => r.data)
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

  /**
   * Sprint 17 — Generate N-dimensional variants via cartesian product.
   * Accepts any number of axes, no artificial limit.
   * Example: axes=[{name:"Couleur",values:["Rouge","Bleu"]},{name:"RAM",values:["8Go","16Go"]}]
   * → creates 4 combinations: Rouge/8Go, Rouge/16Go, Bleu/8Go, Bleu/16Go
   */
  generateVariants(productId: string, data: {
    axes: { name: string; values: string[] }[]
    base_price?: number
    base_currency?: string
  }): Promise<{ created: number; skipped: number; total_combinations: number; message: string }> {
    return client.post(`/api/catalog/products/${productId}/variants/generate`, data).then(r => r.data)
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

  // ── Product Attributes ────────────────────────────────────────────────────

  attributes: {
    list(productId: string): Promise<ProductAttribute[]> {
      return client.get(`/api/catalog/products/${productId}/attributes`).then(r => r.data.data)
    },
    create(productId: string, data: { name: string; code?: string; type?: string; position?: number; values?: Array<{ label: string; value?: string; color_hex?: string }> }): Promise<ProductAttribute> {
      return client.post(`/api/catalog/products/${productId}/attributes`, data).then(r => r.data.data)
    },
    update(productId: string, attrId: string, data: { name?: string; position?: number }): Promise<ProductAttribute> {
      return client.put(`/api/catalog/products/${productId}/attributes/${attrId}`, data).then(r => r.data.data)
    },
    delete(productId: string, attrId: string): Promise<void> {
      return client.delete(`/api/catalog/products/${productId}/attributes/${attrId}`)
    },
    addValue(productId: string, attrId: string, data: { label: string; value?: string; color_hex?: string }): Promise<any> {
      return client.post(`/api/catalog/products/${productId}/attributes/${attrId}/values`, data).then(r => r.data.data)
    },
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
