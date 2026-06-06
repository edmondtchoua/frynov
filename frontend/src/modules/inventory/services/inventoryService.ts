import client from '@/api/client'
import type { AdjustStockPayload, MoveStockPayload, Stock, StockMovement } from '../types'

interface PaginatedStocks {
  data: Stock[]
  meta: { current_page: number; last_page: number; per_page: number; total: number }
}

interface PaginatedMovements {
  data: StockMovement[]
  meta: { current_page: number; last_page: number; per_page: number; total: number }
}

export const inventoryService = {
  // ── Stock list ─────────────────────────────────────────────────────────────
  // The backend returns a Laravel LengthAwarePaginator serialized at root level:
  // { current_page, data, last_page, per_page, total }
  // We map it to the standard { data, meta } shape expected by the views.
  list(params?: Record<string, string | number | boolean>): Promise<PaginatedStocks> {
    return client.get('/api/inventory/stock', { params }).then(r => ({
      data: r.data.data ?? [],
      meta: {
        current_page: r.data.current_page ?? 1,
        last_page:    r.data.last_page    ?? 1,
        per_page:     r.data.per_page     ?? 20,
        total:        r.data.total        ?? 0,
      },
    }))
  },

  // ── Single stock ───────────────────────────────────────────────────────────
  get(productId: string, variantId?: string): Promise<{ stock: Stock; available: number; is_low_stock: boolean }> {
    return client.get(`/api/inventory/stock/${productId}`, { params: variantId ? { variant_id: variantId } : {} }).then(r => r.data)
  },

  // ── Movements ──────────────────────────────────────────────────────────────
  movements(productId: string, params?: { page?: number; per_page?: number; variant_id?: string }): Promise<PaginatedMovements> {
    return client.get(`/api/inventory/stock/${productId}/movements`, { params }).then(r => r.data)
  },

  // ── Low stock alerts ───────────────────────────────────────────────────────
  alerts(): Promise<Stock[]> {
    return client.get('/api/inventory/alerts').then(r => r.data)
  },

  // ── Move in / out / adjust ────────────────────────────────────────────────
  moveIn(productId: string, data: MoveStockPayload) {
    return client.post(`/api/inventory/stock/${productId}/move-in`, data).then(r => r.data)
  },

  moveOut(productId: string, data: MoveStockPayload) {
    return client.post(`/api/inventory/stock/${productId}/move-out`, data).then(r => r.data)
  },

  adjust(productId: string, data: AdjustStockPayload) {
    return client.post(`/api/inventory/stock/${productId}/adjust`, data).then(r => r.data)
  },

  // ── Scan to action ────────────────────────────────────────────────────────
  scan(sku: string, action: 'check' | 'move_in' | 'move_out', quantity?: number) {
    return client.post('/api/inventory/scan', { sku, action, quantity }).then(r => r.data)
  },
}
