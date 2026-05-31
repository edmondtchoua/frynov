export interface Stock {
  id: string
  tenant_id: string
  product_id: string
  variant_id?: string | null
  quantity: number
  reserved_quantity: number
  low_stock_threshold: number
  product?: {
    id: string
    sku: string
    name: string
    status: string
  }
  variant?: {
    id: string
    sku: string
    name: string
  } | null
  available: number
  is_low_stock: boolean
}

export type MovementType = 'in' | 'out' | 'adjustment' | 'return'
export type MovementReason = 'delivery' | 'sale' | 'return' | 'loss' | 'count' | 'manual'

export interface StockMovement {
  id: string
  stock_id: string
  product_id: string
  variant_id?: string | null
  type: MovementType
  quantity: number
  quantity_before: number
  quantity_after: number
  reason: MovementReason
  reference?: string | null
  note?: string | null
  performed_by?: string | null
  created_at: string
}

export interface MoveStockPayload {
  quantity: number
  reason: MovementReason
  reference?: string
  note?: string
  variant_id?: string
}

export interface AdjustStockPayload {
  quantity: number
  note?: string
  variant_id?: string
}
