import client from '@/api/client'

// ── Types ─────────────────────────────────────────────────────────────────────

export interface DashboardKpis {
  revenue_today: number
  revenue_today_change: number | null
  orders_today: number
  orders_today_change: number | null
  active_products: number
  low_stock_alerts: number
}

export interface ChartPoint {
  date: string
  amount: number
  count: number
}

export interface RecentOrder {
  id: string
  number: string
  status: string
  total_amount: number
  currency: string
  customer_id: string | null
  created_at: string
  customer?: { id: string; name: string } | null
}

export interface TopProduct {
  product_id: string
  product_name: string
  sku: string
  total_qty: number
  total_revenue: number
}

export interface DashboardData {
  kpis: DashboardKpis
  revenue_chart: ChartPoint[]
  recent_orders: RecentOrder[]
  top_products: TopProduct[]
}

export interface ByMethod {
  method: string
  amount: number
  count: number
}

export interface SalesData {
  period: string
  total_revenue: number
  total_orders: number
  revenue_chart: ChartPoint[]
  top_products: TopProduct[]
  by_method: ByMethod[]
}

export interface LowStockItem {
  id: string
  product_id: string
  quantity: number
  reserved_quantity: number
  low_stock_threshold: number
  product?: { id: string; name: string; sku: string }
}

export interface MovementSummary {
  type: string
  count: number
  total_qty: number
}

export interface StockData {
  stock_value: number
  total_skus: number
  out_of_stock: number
  low_stock_count: number
  low_stock_items: LowStockItem[]
  recent_movements: MovementSummary[]
}

export type SalesPeriod = '7d' | '30d' | '90d' | '1y'

// RC-5G — reporting produits spéciaux (valorisation agrégé + sérialisé, hors services/digital).
export interface SpecialProductsData {
  aggregate_stock_value: number
  serialized: { in_stock: number; reserved: number; sold: number; in_stock_value: number }
  non_stockable_excluded: number
  warranties: { active_contracts: number; open_claims: number }
  digital: { active_entitlements: number }
  total_inventory_value: number
}

// ── Service ───────────────────────────────────────────────────────────────────

export const reportService = {
  dashboard(): Promise<DashboardData> {
    return client.get('/api/reports/dashboard').then(r => r.data)
  },

  sales(period: SalesPeriod = '7d', warehouseId?: string): Promise<SalesData> {
    return client.get('/api/reports/sales', { params: { period, warehouse_id: warehouseId || undefined } }).then(r => r.data)
  },

  stock(warehouseId?: string): Promise<StockData> {
    return client.get('/api/reports/stock', { params: { warehouse_id: warehouseId || undefined } }).then(r => r.data)
  },

  specialProducts(warehouseId?: string): Promise<SpecialProductsData> {
    return client.get('/api/reports/special-products', { params: { warehouse_id: warehouseId || undefined } }).then(r => r.data)
  },
}

// ── Helpers ───────────────────────────────────────────────────────────────────

// Formatters now live in the shared utils (single source of truth).
// Re-exported here for backward compat with existing imports.
export { formatMoney, formatMoneyCompact } from '@/shared/utils/money'
export { formatDateShort as shortDate } from '@/shared/utils/date'
