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
  net_cash_movements_cents: number
  expected_cash_cents: number
  counted_cash_cents: number | null
  difference_cents: number | null
  opened_by: string | null
  closed_by: string | null
  opened_at: string | null
  closed_at: string | null
  notes: string | null
}

/** RC-16 — one leg of a split payment (cash + Mobile Money in a single sale). */
export interface PosPaymentLeg {
  method: PosPaymentMethod
  amount_cents: number
  reference?: string | null
}

/** RC-16 — a cash-drawer movement (pay-in / pay-out) inside a session. */
export interface CashMovement {
  id: string
  session_id: string
  direction: 'in' | 'out'
  amount_cents: number
  signed_cents: number
  reason: string
  note: string | null
  order_id: string | null
  performed_by: string | null
  created_at: string | null
}

export type CashMovementReason = 'float_add' | 'withdrawal' | 'expense' | 'refund'

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
  /** Legacy single payment… */
  method?: PosPaymentMethod
  reference?: string | null
  /** …or a RC-16 split payment (legs must sum to the total). */
  payments?: PosPaymentLeg[]
  note?: string | null
}

export interface PosRefundLine {
  order_line_id: string
  quantity: number
  condition?: 'resalable' | 'damaged' | 'defective'
}

export interface PosRefundPayload {
  order_id: string
  lines: PosRefundLine[]
  reason: string
  refund_method?: PosPaymentMethod
}
