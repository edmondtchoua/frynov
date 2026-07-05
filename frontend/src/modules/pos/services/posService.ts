import client from '@/api/client'
import type { CashRegisterSession, PosCheckoutPayload } from '../types'

interface CheckoutResult {
  order: { id: string; number: string; status: string; total_amount: number; currency: string }
  payment: { id: string; amount_cents: number; method: string }
  session: CashRegisterSession
}

export const posService = {
  /** The cashier's currently-open session, or null. */
  current(): Promise<CashRegisterSession | null> {
    return client.get('/api/pos/sessions/current').then(r => r.data.data ?? null)
  },

  /** Open a new session with an opening float (in centimes). */
  open(payload: { opening_float_cents: number; label?: string; warehouse_id?: string }): Promise<CashRegisterSession> {
    return client.post('/api/pos/sessions', payload).then(r => r.data.data)
  },

  /** Ring up a sale against an open session. */
  checkout(sessionId: string, payload: PosCheckoutPayload): Promise<CheckoutResult> {
    return client.post(`/api/pos/sessions/${sessionId}/checkout`, payload).then(r => r.data.data)
  },

  /** Close a session, optionally with the counted cash for reconciliation. */
  close(sessionId: string, payload: { counted_cash_cents?: number; notes?: string }): Promise<CashRegisterSession> {
    return client.post(`/api/pos/sessions/${sessionId}/close`, payload).then(r => r.data.data)
  },

  /** Paginated list of past sessions. */
  list(params?: { page?: number; per_page?: number }): Promise<{ data: CashRegisterSession[]; meta: any }> {
    return client.get('/api/pos/sessions', { params }).then(r => ({ data: r.data.data ?? [], meta: r.data.meta ?? {} }))
  },
}
