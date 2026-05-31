<?php

namespace App\Modules\Reports\Http\Controllers;

use App\Modules\Reports\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ReportController extends Controller
{
    public function __construct(private readonly ReportService $service)
    {}

    /**
     * GET /api/reports/dashboard
     * Overview KPIs: revenue today, orders today, active products, low stock.
     * Includes last 7-day revenue chart, last 5 orders, top 5 products (30 days).
     */
    public function dashboard(Request $request): JsonResponse
    {
        return response()->json(
            $this->service->dashboard($request->user()->tenant_id)
        );
    }

    /**
     * GET /api/reports/sales?period=7d|30d|90d|1y
     * Revenue by day, top products, breakdown by payment method.
     */
    public function sales(Request $request): JsonResponse
    {
        $allowed = ['7d', '30d', '90d', '1y'];
        $period  = in_array($request->query('period'), $allowed, true)
            ? $request->query('period')
            : '7d';

        return response()->json(
            $this->service->sales($request->user()->tenant_id, $period)
        );
    }

    /**
     * GET /api/reports/stock
     * Stock value, SKU count, out-of-stock count, low stock items, recent movements.
     */
    public function stock(Request $request): JsonResponse
    {
        return response()->json(
            $this->service->stock($request->user()->tenant_id)
        );
    }
}
