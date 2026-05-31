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

/** Format cents as a human-readable currency string (space-based French locale) */
export function formatMoney(cents: number, currency = 'XOF'): string {
  const amount = cents / 100
  if (currency === 'XOF' || currency === 'XAF') {
    // CFA: no decimals, space thousands separator
    return new Intl.NumberFormat('fr-FR', {
      style: 'currency',
      currency,
      minimumFractionDigits: 0,
      maximumFractionDigits: 0,
    }).format(amount)
  }
  return new Intl.NumberFormat('fr-FR', {
    style: 'currency',
    currency,
    minimumFractionDigits: 0,
    maximumFractionDigits: 2,
  }).format(amount)
}

/** Format cents in a compact form (e.g. "125 k XOF") */
export function formatMoneyCompact(cents: number): string {
  const amount = cents / 100
  if (amount >= 1_000_000) return `${(amount / 1_000_000).toFixed(1)} M`
  if (amount >= 1_000)     return `${(amount / 1_000).toFixed(1)} k`
  return String(Math.round(amount))
}

/** Short date label: "25 mai" */
export function shortDate(iso: string): string {
  return new Date(iso).toLocaleDateString('fr-FR', { day: 'numeric', month: 'short' })
}
