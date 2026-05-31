export type PaymentMethod = 'cash' | 'mobile_money' | 'card' | 'transfer' | 'cheque'

export interface Payment {
  id: string
  order_id: string | null
  order_number?: string | null
  amount_cents: number
  amount: number
  currency: string
  method: PaymentMethod
  reference: string | null
  note: string | null
  paid_at: string
  created_at: string
}

export interface RecordPaymentPayload {
  order_id?: string
  amount_cents: number
  currency: string
  method: PaymentMethod
  reference?: string
  note?: string
}

export interface PaymentSummary {
  data: Payment[]
  balance: number
  total_amount?: number
  is_fully_paid: boolean
}
