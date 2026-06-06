import client from '@/api/client'

export type AdjustmentStatus = 'pending' | 'approved' | 'rejected' | 'executed'

export interface StockAdjustment {
  id: string
  stock_id: string
  product_id: string
  product?: { id: string; name: string; sku: string } | null
  quantity_before: number
  quantity_requested: number
  delta: number
  value_cents: number
  reason: string
  note: string | null
  status: AdjustmentStatus
  rejection_reason: string | null
  created_at: string
}

/** Reasons accepted by the backend (StockAdjustmentRequest::REASONS). */
export const ADJUSTMENT_REASONS = ['loss', 'count', 'manual', 'damage', 'theft', 'correction', 'donation', 'expiry'] as const

export const REASON_LABELS: Record<string, string> = {
  loss: 'Perte', count: 'Comptage', manual: 'Manuel', damage: 'Casse',
  theft: 'Vol', correction: 'Correction', donation: 'Don', expiry: 'Péremption',
}

interface Paginated<T> { data: T[]; current_page: number; last_page: number; total: number }

export const stockAdjustmentService = {
  /** GET /api/inventory/adjustments — pending review queue */
  pending(): Promise<Paginated<StockAdjustment>> {
    return client.get('/api/inventory/adjustments').then(r => r.data)
  },

  /** GET /api/inventory/adjustments/history */
  history(status?: string): Promise<Paginated<StockAdjustment>> {
    return client.get('/api/inventory/adjustments/history', { params: { status: status || undefined } }).then(r => r.data)
  },

  /** POST /api/inventory/adjustments — request an adjustment (new absolute quantity) */
  request(payload: { stock_id: string; new_quantity: number; reason: string; note?: string }) {
    return client.post('/api/inventory/adjustments', payload).then(r => r.data)
  },

  approve(id: string) {
    return client.post(`/api/inventory/adjustments/${id}/approve`).then(r => r.data)
  },

  reject(id: string, reason: string) {
    return client.post(`/api/inventory/adjustments/${id}/reject`, { reason }).then(r => r.data)
  },
}
