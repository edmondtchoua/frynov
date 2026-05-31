import client from '@/api/client'
import type { Payment, PaymentSummary, RecordPaymentPayload } from '../types'

interface PaginatedPayments {
  data: Payment[]
  meta: { current_page: number; last_page: number; per_page: number; total: number }
}

export const paymentService = {
  // ── List all payments ──────────────────────────────────────────────────────
  list(params?: { page?: number; per_page?: number; method?: string; order_id?: string }): Promise<PaginatedPayments> {
    return client.get('/api/payments', { params }).then(r => r.data)
  },

  // ── Single payment ─────────────────────────────────────────────────────────
  get(id: string): Promise<{ data: Payment }> {
    return client.get(`/api/payments/${id}`).then(r => r.data)
  },

  // ── Record a payment ───────────────────────────────────────────────────────
  record(data: RecordPaymentPayload): Promise<{ data: Payment; balance: number; is_fully_paid: boolean }> {
    return client.post('/api/payments', data).then(r => r.data)
  },

  // ── Void (soft-delete) ─────────────────────────────────────────────────────
  void(id: string): Promise<void> {
    return client.delete(`/api/payments/${id}`).then(r => r.data)
  },

  // ── Payments for an order ──────────────────────────────────────────────────
  forOrder(orderId: string): Promise<PaymentSummary> {
    return client.get(`/api/orders/${orderId}/payments`).then(r => r.data)
  },
}
