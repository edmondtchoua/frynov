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

    public function sales(string $tenantId, string $period = '7d', ?string $warehouseId = null): array
    {
        $days = match ($period) {
            '30d'  => 30,
            '90d'  => 90,
            '1y'   => 365,
            default => 7,
        };

        $from = now()->subDays($days - 1)->startOfDay();

        $chart = $this->revenueByDay($tenantId, $days, $warehouseId);

        $totalRevenue = array_sum(array_column($chart, 'amount'));
        $totalOrders  = array_sum(array_column($chart, 'count'));

        $topProducts = $this->topProducts($tenantId, $days, 10, $warehouseId);

        $byMethod = Payment::where('tenant_id', $tenantId)
            ->where('paid_at', '>=', $from)
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
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

    public function stock(string $tenantId, ?string $warehouseId = null): array
    {
        // Stock value = quantity × cost (fallback to price if cost not set)
        $stockValue = (int) (Stock::join('products', 'stocks.product_id', '=', 'products.id')
            ->where('stocks.tenant_id', $tenantId)
            ->whereNull('products.deleted_at')
            ->when($warehouseId, fn ($q) => $q->where('stocks.warehouse_id', $warehouseId))
            ->selectRaw('SUM(stocks.quantity * COALESCE(NULLIF(products.cost_amount, 0), products.price_amount)) as total_value')
            ->value('total_value') ?? 0);

        $totalSkus = Stock::where('tenant_id', $tenantId)
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->count();

        $outOfStock = Stock::where('tenant_id', $tenantId)
            ->where('quantity', '<=', 0)
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->count();

        $lowStockItems = Stock::where('tenant_id', $tenantId)
            ->where('low_stock_threshold', '>', 0)
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->whereRaw('(quantity - reserved_quantity) <= low_stock_threshold')
            ->with('product:id,name,sku')
            ->orderByRaw('(quantity - reserved_quantity) / NULLIF(low_stock_threshold, 0) ASC')
            ->limit(10)
            ->get(['id', 'product_id', 'quantity', 'reserved_quantity', 'low_stock_threshold'])
            ->map(fn ($s) => array_merge($s->toArray(), ['available' => max(0, $s->quantity - $s->reserved_quantity)]));

        $recentMovements = StockMovement::where('tenant_id', $tenantId)
            ->where('created_at', '>=', now()->subDays(30))
            ->when($warehouseId, fn ($q) => $q->whereIn(
                'stock_id',
                Stock::where('tenant_id', $tenantId)->where('warehouse_id', $warehouseId)->select('id'),
            ))
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

    private function revenueByDay(string $tenantId, int $days, ?string $warehouseId = null): array
    {
        $from = now()->subDays($days - 1)->startOfDay();

        $rows = Payment::where('tenant_id', $tenantId)
            ->where('paid_at', '>=', $from)
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
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

    private function topProducts(string $tenantId, int $days, int $limit, ?string $warehouseId = null): Collection
    {
        $from = now()->subDays($days - 1)->startOfDay();

        return OrderLine::join('orders', 'order_lines.order_id', '=', 'orders.id')
            ->where('orders.tenant_id', $tenantId)
            ->where('orders.status', '!=', Order::STATUS_CANCELLED)
            ->where('orders.created_at', '>=', $from)
            ->when($warehouseId, fn ($q) => $q->where('orders.warehouse_id', $warehouseId))
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

    // ── ABC Classification (Pareto 80/15/5) ──────────────────────────────────

    public function abcClassification(string $tenantId, int $days = 90): array
    {
        $from = now()->subDays($days)->startOfDay();

        $productRevenue = OrderLine::join('orders', 'order_lines.order_id', '=', 'orders.id')
            ->where('orders.tenant_id', $tenantId)
            ->whereNotIn('orders.status', [Order::STATUS_CANCELLED])
            ->where('orders.created_at', '>=', $from)
            ->selectRaw('
                order_lines.product_id,
                order_lines.name        AS product_name,
                order_lines.sku,
                SUM(order_lines.quantity * order_lines.unit_price_cents) AS revenue_cents,
                SUM(order_lines.quantity)                                 AS total_qty
            ')
            ->groupBy('order_lines.product_id', 'order_lines.name', 'order_lines.sku')
            ->orderByDesc('revenue_cents')
            ->get();

        $totalRevenue = $productRevenue->sum('revenue_cents');

        if ($totalRevenue == 0) {
            return ['period_days' => $days, 'total_revenue' => 0, 'summary' => [], 'items' => []];
        }

        $cumulative = 0;
        $classified = $productRevenue->map(function ($p) use ($totalRevenue, &$cumulative) {
            $share      = $p->revenue_cents / $totalRevenue * 100;
            $cumulative += $share;

            return [
                'product_id'        => $p->product_id,
                'product_name'      => $p->product_name,
                'sku'               => $p->sku,
                'revenue_cents'     => (int) $p->revenue_cents,
                'revenue_share_pct' => round($share, 2),
                'cumulative_pct'    => round($cumulative, 2),
                'total_qty_sold'    => (int) $p->total_qty,
                'abc_class'         => $cumulative <= 80 ? 'A' : ($cumulative <= 95 ? 'B' : 'C'),
            ];
        });

        $summary = $classified->groupBy('abc_class')
            ->map(fn ($group, $class) => [
                'class'             => $class,
                'product_count'     => $group->count(),
                'revenue_cents'     => $group->sum('revenue_cents'),
                'revenue_share_pct' => round($group->sum('revenue_share_pct'), 1),
            ])->values();

        return [
            'period_days'   => $days,
            'total_revenue' => (int) $totalRevenue,
            'summary'       => $summary,
            'items'         => $classified->values(),
        ];
    }

    // ── KPIs: DSI / Rotation / Fill Rate / Dead Stock ─────────────────────────

    public function inventoryKpis(string $tenantId, int $days = 90): array
    {
        $from = now()->subDays($days)->startOfDay();

        $totalStockValue = (int) \Illuminate\Support\Facades\DB::table('stocks')
            ->where('tenant_id', $tenantId)
            ->sum('total_value_cents');

        $cogs = (int) OrderLine::join('orders', 'order_lines.order_id', '=', 'orders.id')
            ->join('stocks', fn ($j) => $j
                ->on('stocks.product_id', '=', 'order_lines.product_id')
                ->where('stocks.tenant_id', $tenantId)
            )
            ->where('orders.tenant_id', $tenantId)
            ->whereNotIn('orders.status', [Order::STATUS_CANCELLED])
            ->where('orders.created_at', '>=', $from)
            ->sum(\Illuminate\Support\Facades\DB::raw('order_lines.quantity * stocks.unit_cost_cents'));

        $dsi          = ($cogs > 0) ? round($totalStockValue / $cogs * $days, 1) : null;
        $rotationRate = ($totalStockValue > 0) ? round($cogs / $totalStockValue * (365 / $days), 2) : null;

        $totalOrders     = Order::where('tenant_id', $tenantId)
            ->whereIn('status', [Order::STATUS_CONFIRMED, Order::STATUS_FULFILLED])
            ->where('created_at', '>=', $from)->count();
        $fulfilledOrders = Order::where('tenant_id', $tenantId)
            ->where('status', Order::STATUS_FULFILLED)
            ->where('created_at', '>=', $from)->count();

        $fillRate = $totalOrders > 0 ? round($fulfilledOrders / $totalOrders * 100, 1) : null;

        $deadStockValue = (int) \Illuminate\Support\Facades\DB::table('stocks')
            ->where('stocks.tenant_id', $tenantId)
            ->where('stocks.quantity', '>', 0)
            ->whereNotExists(fn ($q) => $q->from('stock_movements')
                ->whereColumn('stock_movements.stock_id', 'stocks.id')
                ->where('stock_movements.created_at', '>=', now()->subDays(180))
            )
            ->sum('total_value_cents');

        $deadStockRate = $totalStockValue > 0
            ? round($deadStockValue / $totalStockValue * 100, 1)
            : 0;

        return [
            'period_days'         => $days,
            'dsi'                 => $dsi,
            'rotation_rate'       => $rotationRate,
            'fill_rate_pct'       => $fillRate,
            'dead_stock_rate_pct' => $deadStockRate,
            'cogs_cents'          => $cogs,
            'total_stock_value'   => $totalStockValue,
            'total_orders'        => $totalOrders,
            'fulfilled_orders'    => $fulfilledOrders,
        ];
    }

    // ── Stock reconciliation report ───────────────────────────────────────────

    public function stockReconciliation(string $tenantId): array
    {
        $lines = \Illuminate\Support\Facades\DB::table('stocks')
            ->join('products', 'stocks.product_id', '=', 'products.id')
            ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
            ->where('stocks.tenant_id', $tenantId)
            ->whereNull('products.deleted_at')
            ->selectRaw("
                COALESCE(categories.name, 'Sans catégorie') AS category_name,
                COUNT(stocks.id)                              AS sku_count,
                SUM(stocks.quantity)                          AS total_qty,
                SUM(stocks.quantity - stocks.reserved_quantity) AS available_qty,
                SUM(stocks.total_value_cents)                 AS erp_value_cents,
                AVG(stocks.unit_cost_cents)                   AS avg_cmup_cents
            ")
            ->groupBy('categories.name')
            ->orderByDesc('erp_value_cents')
            ->get();

        $movementsSummary = StockMovement::where('tenant_id', $tenantId)
            ->where('created_at', '>=', now()->subMonth())
            ->selectRaw('reason, type, COUNT(*) as count, SUM(quantity) as total_qty')
            ->groupBy('reason', 'type')
            ->get();

        return [
            'generated_at'      => now()->toISOString(),
            'total_erp_value'   => (int) $lines->sum('erp_value_cents'),
            'lines_by_category' => $lines,
            'movements_summary' => $movementsSummary,
        ];
    }
}
