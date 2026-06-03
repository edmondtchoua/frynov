export type ProductStatus = 'draft' | 'active' | 'archived'
export type ProductType   = 'simple' | 'variable' | 'service' | 'kit'

export interface ProductPrice {
  amount: number
  currency: string
  formatted: string
}

export interface ProductVariant {
  id: string
  sku: string
  name: string
  label?: string
  price: ProductPrice
  compare_at_price?: ProductPrice | null
  barcode?: string
  weight_kg?: number
  stock?: number
  is_active?: boolean
  attributes?: Record<string, string>
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

export interface ProductSupplierSummary {
  id: string
  name: string
  code?: string | null
}

export interface Product {
  id: string
  sku: string
  name: string
  description?: string
  status: ProductStatus
  product_type: ProductType
  category?: Category | null
  supplier?: ProductSupplierSummary | null
  supplier_id?: string | null
  price: ProductPrice
  compare_at_price?: ProductPrice | null
  cost?: ProductPrice | null
  is_on_sale: boolean
  has_variants: boolean
  variants?: ProductVariant[]
  barcode?: string
  internal_barcode?: string
  gtin?: string
  barcode_type?: string
  barcode_source?: string
  barcode_auto_generated?: boolean
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
  product_type?: ProductType
  category_id?: string
  supplier_id?: string
  barcode?: string
  internal_barcode?: string
  gtin?: string
  weight_kg?: number
  has_variants?: boolean
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

// ── Stock types ───────────────────────────────────────────────────────────────

export interface StockWarehouseRow {
  warehouse_id: string | null
  warehouse_name: string
  quantity: number
  reserved: number
  available: number
  low_stock: boolean
  unit_cost_cents: number
  total_value_cents: number
}

export interface StockVariantRow {
  variant_id: string
  quantity: number
  reserved: number
  available: number
  low_stock: boolean
}

export interface ProductStockSummary {
  total_quantity: number
  reserved_quantity: number
  available_quantity: number
  low_stock_count: number
  by_warehouse: StockWarehouseRow[]
  by_variant: StockVariantRow[]
}

export interface StockMovementItem {
  id: string
  type: 'in' | 'out' | 'adjustment' | 'return'
  quantity: number
  quantity_before: number
  quantity_after: number
  reason: string
  reference?: string
  note?: string
  performed_by?: string
  created_at: string
}

// ── Attribute types ───────────────────────────────────────────────────────────

export interface ProductAttributeValue {
  id: string
  label: string
  value: string
  color_hex?: string | null
  position: number
}

export interface ProductAttribute {
  id: string
  name: string
  code: string
  type: 'text' | 'color' | 'size' | 'select' | 'boolean'
  position: number
  is_global: boolean
  values: ProductAttributeValue[]
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
