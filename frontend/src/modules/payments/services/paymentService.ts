import client from '@/api/client'
import type { Payment, PaymentSummary, RecordPaymentPayload } from '../types'

interface PaginatedPayments {
  data: Payment[]
  meta: { current_page: number; last_page: number; per_page: number; total: number }
}

export const paymentService = {
  // ── List all payments ──────────────────────────────────────────────────────
  list(params?: { page?: number; per_page?: number; method?: string; order_id?: string; warehouse_id?: string }): Promise<PaginatedPayments> {
    return client.get('/api/payments', { params }).then(r => r.data)
  },

  // ── Single payment ─────────────────────────────────────────────────────────
  get(id: string): Promise<{ data: Payment }> {
    return client.get(`/api/payments/${id}`).then(r => r.data)
  },

  // ── Record a payment ───────────────────────────────────────────────────────
  // RC-20 (C-10) — clé d'idempotence générée côté client : un retry (timeout, double clic)
  // rejoue la MÊME clé → le back renvoie le paiement existant au lieu d'en créer un second.
  record(data: RecordPaymentPayload): Promise<{ data: Payment; balance: number; is_fully_paid: boolean }> {
    const idempotencyKey = (() => {
      try { return crypto.randomUUID() } catch { return `pay-${Date.now()}-${Math.floor(Math.random() * 1e9)}` }
    })()
    return client
      .post('/api/payments', data, { headers: { 'X-Idempotency-Key': idempotencyKey } })
      .then(r => r.data)
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
