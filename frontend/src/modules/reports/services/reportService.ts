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

// ── Service ───────────────────────────────────────────────────────────────────

export const reportService = {
  dashboard(): Promise<DashboardData> {
    return client.get('/api/reports/dashboard').then(r => r.data)
  },

  sales(period: SalesPeriod = '7d'): Promise<SalesData> {
    return client.get('/api/reports/sales', { params: { period } }).then(r => r.data)
  },

  stock(): Promise<StockData> {
    return client.get('/api/reports/stock').then(r => r.data)
  },
}

// ── Helpers ───────────────────────────────────────────────────────────────────

// Formatters now live in the shared utils (single source of truth).
// Re-exported here for backward compat with existing imports.
export { formatMoney, formatMoneyCompact } from '@/shared/utils/money'
export { formatDateShort as shortDate } from '@/shared/utils/date'
