import client from '@/api/client'
import type { AdjustStockPayload, MoveStockPayload, Stock, StockMovement } from '../types'

// ── Matrice d'entrée de stock variantes × entrepôts (RC-4) ──────────────────
export interface StockMatrixCell {
  quantity: number
  available: number
  unit_cost_cents: number
}
export interface StockMatrixWarehouse {
  id: string
  name: string
  code: string | null
  is_default: boolean
}
export interface StockMatrixRow {
  variant_id: string | null
  label: string
  sku: string
  cells: Record<string, StockMatrixCell>
}
export interface VariantStockMatrix {
  product_id: string
  product_name: string
  has_variants: boolean
  warehouses: StockMatrixWarehouse[]
  rows: StockMatrixRow[]
}
export interface DeliveryItem {
  product_id: string
  variant_id?: string | null
  warehouse_id?: string | null
  quantity: number
  unit_cost_cents?: number
}

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

  // ── Variant × warehouse stock matrix (RC-4) ────────────────────────────────
  variantStockMatrix(productId: string): Promise<VariantStockMatrix> {
    return client.get(`/api/catalog/products/${productId}/variant-stock-matrix`).then(r => r.data)
  },

  // ── Batch delivery (one line per variant × warehouse) ──────────────────────
  receiveDelivery(items: DeliveryItem[], reference?: string) {
    return client.post('/api/inventory/deliveries', { items, reference: reference || undefined }).then(r => r.data)
  },
}
