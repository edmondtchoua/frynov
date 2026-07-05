import client from '@/api/client'
import type {
  CashMovement,
  CashRegisterSession,
  PosCheckoutPayload,
  PosReceipt,
  PosRefundPayload,
} from '../types'

interface CheckoutResult {
  order: { id: string; number: string; status: string; total_amount: number; currency: string; lines: any[] }
  payment: { id: string; amount_cents: number; method: string } | null
  payments: { id: string; amount_cents: number; method: string }[]
  session: CashRegisterSession
}

interface CashMovementResult {
  movement: CashMovement
  session: CashRegisterSession
}

interface RefundResult {
  return: { id: string; number: string; status: string; refund_amount_cents: number }
  movement: CashMovement | null
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

  /**
   * Ring up a sale against an open session (single or split payment).
   *
   * RC-22 (P0 compta/offline) — `idempotencyKey` : la MÊME clé rejouée (retry réseau, resync
   * offline) renvoie la vente déjà créée au lieu d'en créer une seconde. La file offline réutilise
   * l'id client de la vente en attente comme clé stable entre tentatives.
   */
  checkout(sessionId: string, payload: PosCheckoutPayload, idempotencyKey?: string): Promise<CheckoutResult> {
    return client
      .post(`/api/pos/sessions/${sessionId}/checkout`, payload, {
        headers: idempotencyKey ? { 'X-Idempotency-Key': idempotencyKey } : undefined,
      })
      .then(r => r.data.data)
  },

  /** Close a session, optionally with the counted cash for reconciliation. */
  close(sessionId: string, payload: { counted_cash_cents?: number; notes?: string }): Promise<CashRegisterSession> {
    return client.post(`/api/pos/sessions/${sessionId}/close`, payload).then(r => r.data.data)
  },

  /** Paginated list of past sessions. */
  list(params?: { page?: number; per_page?: number }): Promise<{ data: CashRegisterSession[]; meta: any }> {
    return client.get('/api/pos/sessions', { params }).then(r => ({ data: r.data.data ?? [], meta: r.data.meta ?? {} }))
  },

  // ── RC-16 caisse approfondie ───────────────────────────────────────────────

  /** List the drawer movements (pay-ins / pay-outs) of a session. */
  movements(sessionId: string): Promise<CashMovement[]> {
    return client.get(`/api/pos/sessions/${sessionId}/movements`).then(r => r.data.data ?? [])
  },

  /** Record a cash-drawer movement (pay-in or pay-out). */
  cashMovement(
    sessionId: string,
    payload: { direction: 'in' | 'out'; amount_cents: number; reason?: string; note?: string },
  ): Promise<CashMovementResult> {
    return client.post(`/api/pos/sessions/${sessionId}/cash-movement`, payload).then(r => r.data.data)
  },

  /** Refund a sale at the till (restocks resalable items, cash leg pays out of the drawer). */
  refund(sessionId: string, payload: PosRefundPayload): Promise<RefundResult> {
    return client.post(`/api/pos/sessions/${sessionId}/refund`, payload).then(r => r.data.data)
  },

  /** RC-19 — structured receipt of a POS sale (print / reprint). */
  receipt(orderId: string): Promise<PosReceipt> {
    return client.get(`/api/pos/orders/${orderId}/receipt`).then(r => r.data.data)
  },
}
