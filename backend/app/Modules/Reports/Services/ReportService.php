<?php

namespace App\Modules\Reports\Services;

use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\Stock;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderLine;
use App\Modules\Payments\Models\Payment;
use Illuminate\Support\Collection;

class ReportService
{
    // ── Dashboard ─────────────────────────────────────────────────────────────

    public function dashboard(string $tenantId): array
    {
        $today     = now()->toDateString();
        $yesterday = now()->subDay()->toDateString();

        // Revenue today / yesterday (from payments)
        $revenueToday = (int) Payment::where('tenant_id', $tenantId)
            ->whereDate('paid_at', $today)
            ->sum('amount_cents');

        $revenueYesterday = (int) Payment::where('tenant_id', $tenantId)
            ->whereDate('paid_at', $yesterday)
            ->sum('amount_cents');

        $revenueChange = $revenueYesterday > 0
            ? round(($revenueToday - $revenueYesterday) / $revenueYesterday * 100, 1)
            : null;

        // Orders today / yesterday (excluding cancelled)
        $ordersToday = Order::where('tenant_id', $tenantId)
            ->whereDate('created_at', $today)
            ->where('status', '!=', Order::STATUS_CANCELLED)
            ->count();

        $ordersYesterday = Order::where('tenant_id', $tenantId)
            ->whereDate('created_at', $yesterday)
            ->where('status', '!=', Order::STATUS_CANCELLED)
            ->count();

        $ordersChange = $ordersYesterday > 0
            ? round(($ordersToday - $ordersYesterday) / $ordersYesterday * 100, 1)
            : null;

        // Active products
        $activeProducts = Product::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->count();

        // Low stock alerts (quantity at or below threshold, threshold > 0)
        $lowStockAlerts = Stock::where('tenant_id', $tenantId)
            ->where('low_stock_threshold', '>', 0)
            ->whereColumn('quantity', '<=', 'low_stock_threshold')
            ->count();

        // Revenue chart — last 7 days
        $revenueChart = $this->revenueByDay($tenantId, 7);

        // Recent orders — last 5
        $recentOrders = Order::where('tenant_id', $tenantId)
            ->with('customer:id,name')
            ->latest()
            ->limit(5)
            ->get(['id', 'number', 'status', 'total_amount', 'currency', 'customer_id', 'created_at']);

        // Top products — last 30 days
        $topProducts = $this->topProducts($tenantId, 30, 5);

        return [
            'kpis' => [
                'revenue_today'        => $revenueToday,
                'revenue_today_change' => $revenueChange,
                'orders_today'         => $ordersToday,
                'orders_today_change'  => $ordersChange,
                'active_products'      => $activeProducts,
                'low_stock_alerts'     => $lowStockAlerts,
            ],
            'revenue_chart' => $revenueChart,
            'recent_orders' => $recentOrders,
            'top_products'  => $topProducts,
        ];
    }

    // ── Sales report ──────────────────────────────────────────────────────────

    public function sales(string $tenantId, string $period = '7d'): array
    {
        $days = match ($period) {
            '30d'  => 30,
            '90d'  => 90,
            '1y'   => 365,
            default => 7,
        };

        $from = now()->subDays($days - 1)->startOfDay();

        $chart = $this->revenueByDay($tenantId, $days);

        $totalRevenue = array_sum(array_column($chart, 'amount'));
        $totalOrders  = array_sum(array_column($chart, 'count'));

        $topProducts = $this->topProducts($tenantId, $days, 10);

        $byMethod = Payment::where('tenant_id', $tenantId)
            ->where('paid_at', '>=', $from)
            ->selectRaw('method, SUM(amount_cents) as amount, COUNT(*) as count')
            ->groupBy('method')
            ->orderByDesc('amount')
            ->get();

        return [
            'period'        => $period,
            'total_revenue' => $totalRevenue,
            'total_orders'  => $totalOrders,
            'revenue_chart' => $chart,
            'top_products'  => $topProducts,
            'by_method'     => $byMethod,
        ];
    }

    // ── Stock report ──────────────────────────────────────────────────────────

    public function stock(string $tenantId): array
    {
        // Stock value = quantity × cost (fallback to price if cost not set)
        $stockValue = (int) (Stock::join('products', 'stocks.product_id', '=', 'products.id')
            ->where('stocks.tenant_id', $tenantId)
            ->whereNull('products.deleted_at')
            ->selectRaw('SUM(stocks.quantity * COALESCE(NULLIF(products.cost_amount, 0), products.price_amount)) as total_value')
            ->value('total_value') ?? 0);

        $totalSkus = Stock::where('tenant_id', $tenantId)->count();

        $outOfStock = Stock::where('tenant_id', $tenantId)
            ->where('quantity', '<=', 0)
            ->count();

        $lowStockItems = Stock::where('tenant_id', $tenantId)
            ->where('quantity', '>', 0)
            ->where('low_stock_threshold', '>', 0)
            ->whereColumn('quantity', '<=', 'low_stock_threshold')
            ->with('product:id,name,sku')
            ->orderByRaw('CAST(quantity AS REAL) / NULLIF(low_stock_threshold, 0) ASC')
            ->limit(10)
            ->get(['id', 'product_id', 'quantity', 'reserved_quantity', 'low_stock_threshold']);

        $recentMovements = StockMovement::where('tenant_id', $tenantId)
            ->where('created_at', '>=', now()->subDays(30))
            ->selectRaw('type, COUNT(*) as count, SUM(ABS(quantity)) as total_qty')
            ->groupBy('type')
            ->get();

        return [
            'stock_value'      => $stockValue,
            'total_skus'       => $totalSkus,
            'out_of_stock'     => $outOfStock,
            'low_stock_count'  => $lowStockItems->count(),
            'low_stock_items'  => $lowStockItems,
            'recent_movements' => $recentMovements,
        ];
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function revenueByDay(string $tenantId, int $days): array
    {
        $from = now()->subDays($days - 1)->startOfDay();

        $rows = Payment::where('tenant_id', $tenantId)
            ->where('paid_at', '>=', $from)
            ->selectRaw('DATE(paid_at) as date, SUM(amount_cents) as amount, COUNT(*) as count')
            ->groupByRaw('DATE(paid_at)')
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        $chart = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date    = now()->subDays($i)->toDateString();
            $row     = $rows[$date] ?? null;
            $chart[] = [
                'date'   => $date,
                'amount' => (int) ($row?->amount ?? 0),
                'count'  => (int) ($row?->count ?? 0),
            ];
        }

        return $chart;
    }

    private function topProducts(string $tenantId, int $days, int $limit): Collection
    {
        $from = now()->subDays($days - 1)->startOfDay();

        return OrderLine::join('orders', 'order_lines.order_id', '=', 'orders.id')
            ->where('orders.tenant_id', $tenantId)
            ->where('orders.status', '!=', Order::STATUS_CANCELLED)
            ->where('orders.created_at', '>=', $from)
            ->selectRaw(
                'order_lines.product_id,
                 order_lines.name    AS product_name,
                 order_lines.sku,
                 SUM(order_lines.quantity)                                    AS total_qty,
                 SUM(order_lines.unit_price_cents * order_lines.quantity)     AS total_revenue'
            )
            ->groupBy('order_lines.product_id', 'order_lines.name', 'order_lines.sku')
            ->orderByDesc('total_revenue')
            ->limit($limit)
            ->get();
    }
}
