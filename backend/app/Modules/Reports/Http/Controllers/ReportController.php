<?php

namespace App\Modules\Reports\Http\Controllers;

use App\Modules\Inventory\Support\WarehouseScope;
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
            $this->service->sales($request->user()->tenant_id, $period, WarehouseScope::resolve($request->user(), $request->query('warehouse_id')))
        );
    }

    /**
     * GET /api/reports/stock
     * Stock value, SKU count, out-of-stock count, low stock items, recent movements.
     */
    public function stock(Request $request): JsonResponse
    {
        return response()->json(
            $this->service->stock($request->user()->tenant_id, WarehouseScope::resolve($request->user(), $request->query('warehouse_id')))
        );
    }

    /**
     * GET /api/reports/special-products
     * RC-5G — valorisation produits spéciaux : stock agrégé + unités sérialisées en stock, exclut
     * services/digital ; rappel garanties/SAV/digital.
     */
    public function specialProducts(Request $request): JsonResponse
    {
        return response()->json(
            $this->service->specialProducts($request->user()->tenant_id, WarehouseScope::resolve($request->user(), $request->query('warehouse_id')))
        );
    }

    // ── RC-17 (M-2) — rapports développés+testés mais jamais exposés (code mort applicatif) ──

    /**
     * GET /api/reports/abc?days=30|90|180|365
     * Classification ABC (Pareto 80/15/5) du chiffre d'affaires par produit.
     */
    public function abc(Request $request): JsonResponse
    {
        $days = (int) $request->query('days', 90);
        $days = in_array($days, [30, 90, 180, 365], true) ? $days : 90;

        return response()->json(
            $this->service->abcClassification($request->user()->tenant_id, $days)
        );
    }

    /**
     * GET /api/reports/inventory-kpis?days=30|90|180|365
     * KPIs d'inventaire : DSI, taux de rotation, fill rate, stock mort.
     */
    public function inventoryKpis(Request $request): JsonResponse
    {
        $days = (int) $request->query('days', 90);
        $days = in_array($days, [30, 90, 180, 365], true) ? $days : 90;

        return response()->json(
            $this->service->inventoryKpis($request->user()->tenant_id, $days)
        );
    }

    /**
     * GET /api/reports/reconciliation
     * Réconciliation de stock : valorisation ERP par catégorie + mouvements du dernier mois.
     */
    public function reconciliation(Request $request): JsonResponse
    {
        return response()->json(
            $this->service->stockReconciliation($request->user()->tenant_id)
        );
    }
}
