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
  list(params?: { page?: number; per_page?: number }): Promise<PaginatedStocks> {
    return client.get('/api/inventory/stock', { params }).then(r => r.data)
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
