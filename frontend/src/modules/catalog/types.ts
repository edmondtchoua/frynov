export type ProductStatus = 'draft' | 'active' | 'archived'

export interface ProductPrice {
  amount: number
  currency: string
  formatted: string
}

export interface ProductVariant {
  id: string
  sku: string
  name: string
  price: ProductPrice
  compare_at_price?: ProductPrice | null
  barcode?: string
  weight_kg?: number
  stock?: number
}

export interface Category {
  id: string
  name: string
  slug: string
  parent_id?: string | null
  description?: string
  sort_order: number
  is_active: boolean
}

export interface Product {
  id: string
  sku: string
  name: string
  description?: string
  status: ProductStatus
  category?: Category | null
  price: ProductPrice
  compare_at_price?: ProductPrice | null
  is_on_sale: boolean
  has_variants: boolean
  variants?: ProductVariant[]
  barcode?: string
  weight_kg?: number
  metadata?: Record<string, unknown>
  created_at: string
  updated_at: string
}

export interface CreateProductPayload {
  name: string
  sku?: string
  sku_prefix?: string
  description?: string
  price_amount: number
  price_currency: string
  compare_at_price_amount?: number
  cost_amount?: number
  status?: ProductStatus
  category_id?: string
  barcode?: string
  weight_kg?: number
}

export interface CreateCategoryPayload {
  name: string
  slug?: string
  parent_id?: string
  description?: string
  sort_order?: number
  is_active?: boolean
}

// ── Variant types ─────────────────────────────────────────────────────────────

export interface ProductVariantFull {
  id: string
  product_id: string
  sku: string
  label: string
  name?: string
  attributes: Record<string, string>
  price?: ProductPrice | null
  compare_at_price?: ProductPrice | null
  cost_amount?: number | null
  barcode?: string | null
  sort_order: number
  is_active: boolean
  stock?: number
}

export interface CreateVariantPayload {
  sku?: string
  name?: string
  attributes?: Record<string, string>
  price_amount?: number | null
  price_currency?: string
  cost_amount?: number | null
  barcode?: string | null
  sort_order?: number
  is_active?: boolean
}

// ── Label / print types ───────────────────────────────────────────────────────

export interface LabelBatchItem {
  product_id: string
  variant_id?: string | null
  copies: number
  product_name?: string
  variant_label?: string
  sku?: string
}

export interface LabelBatchPayload {
  items: LabelBatchItem[]
  format: 'thermal' | 'a4sheet'
  show_price: boolean
  show_qr: boolean
}
