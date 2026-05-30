export type OrderStatus = 'draft' | 'confirmed' | 'fulfilled' | 'cancelled'

export interface OrderLine {
  id: string
  order_id: string
  product_id: string
  variant_id: string | null
  sku: string
  name: string
  quantity: number
  unit_price_cents: number
}

export interface Order {
  id: string
  tenant_id: string
  customer_id: string | null
  number: string
  status: OrderStatus
  total_amount: number
  currency: string
  note: string | null
  performed_by: string | null
  fulfilled_at: string | null
  cancelled_at: string | null
  created_at: string
  updated_at: string
  lines: OrderLine[]
}

export interface CreateOrderItem {
  product_id: string
  variant_id?: string | null
  quantity: number
  unit_price_cents?: number
}

export interface CreateOrderPayload {
  items: CreateOrderItem[]
  customer_id?: string | null
  note?: string | null
}
