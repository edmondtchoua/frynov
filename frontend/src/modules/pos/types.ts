export type PosPaymentMethod = 'cash' | 'mobile_money' | 'card' | 'transfer' | 'cheque'

export interface CashRegisterSession {
  id: string
  warehouse_id: string | null
  label: string | null
  status: 'open' | 'closed'
  opening_float_cents: number
  total_sales_cents: number
  cash_sales_cents: number
  sales_count: number
  expected_cash_cents: number
  counted_cash_cents: number | null
  difference_cents: number | null
  opened_by: string | null
  closed_by: string | null
  opened_at: string | null
  closed_at: string | null
  notes: string | null
}

export interface PosCartItem {
  product_id: string
  variant_id?: string | null
  name: string
  sku: string
  unit_price_cents: number
  quantity: number
}

export interface PosCheckoutPayload {
  items: { product_id: string; variant_id?: string | null; quantity: number }[]
  customer_id?: string | null
  method: PosPaymentMethod
  reference?: string | null
  note?: string | null
}
