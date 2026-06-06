<?php

namespace App\Modules\Inventory\Http\Controllers;

use App\Modules\Inventory\Exceptions\InsufficientStockException;
use App\Modules\Inventory\Exceptions\ProductNotFoundException;
use App\Modules\Inventory\Exceptions\StockLockException;
use App\Modules\Inventory\Http\Requests\AdjustStockRequest;
use App\Modules\Inventory\Http\Requests\BatchDeliveryRequest;
use App\Modules\Inventory\Http\Requests\MoveStockRequest;
use App\Modules\Inventory\Http\Requests\ScanActionRequest;
use App\Modules\Inventory\Models\Stock;
use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Inventory\Services\StockService;
use App\Modules\Inventory\Support\WarehouseScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class InventoryController extends Controller
{
    public function __construct(
        private readonly StockService $stockService,
        private readonly InventoryService $inventoryService,
    ) {}

    // GET /api/inventory/stock
    public function index(Request $request): JsonResponse
    {
        $query = Stock::where('stocks.tenant_id', $request->user()->tenant_id)
            ->with(['product.category', 'variant', 'warehouse:id,name,code,type']);

        // Filter by warehouse + enforce per-agency access scoping (multi-site).
        $warehouseIds = WarehouseScope::resolve($request->user(), $request->query('warehouse_id'));
        if ($warehouseIds !== null) {
            $query->whereIn('stocks.warehouse_id', $warehouseIds);
        }

        // Search by product name or SKU
        if ($search = $request->query('search')) {
            $query->whereHas('product', fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('sku',  'like', "%{$search}%")
            );
        }

        // Filter by category (including sub-categories)
        if ($categoryId = $request->query('category_id')) {
            $query->whereHas('product', fn ($q) => $q->where('category_id', $categoryId));
        }

        // Filter low stock
        if ($request->boolean('low_stock')) {
            $query->where('low_stock_threshold', '>', 0)
                  ->whereRaw('(quantity - reserved_quantity) <= low_stock_threshold');
        }

        $stocks = $query->latest('stocks.updated_at')->paginate(50);

        return response()->json($stocks);
    }

    // GET /api/inventory/stock/{productId}
    public function show(Request $request, string $productId): JsonResponse
    {
        $variantId = $request->query('variant_id');
        $tenantId  = $request->user()->tenant_id;

        // Sprint 11: verify product belongs to tenant before accessing its stock
        $product = \App\Modules\Catalog\Models\Product::where('tenant_id', $tenantId)
            ->where('id', $productId)->firstOrFail();

        $stock = $this->stockService->findOrCreate($tenantId, $productId, $variantId);
        $stock->load(['product', 'variant']);

        return response()->json([
            'stock'            => $stock,
            'available'        => $stock->available(),
            'is_low_stock'     => $stock->isLowStock(),
        ]);
    }

    // GET /api/inventory/stock/{productId}/movements
    public function movements(Request $request, string $productId): JsonResponse
    {
        $variantId = $request->query('variant_id');
        $tenantId  = $request->user()->tenant_id;

        $product = \App\Modules\Catalog\Models\Product::where('tenant_id', $tenantId)
            ->where('id', $productId)->firstOrFail();

        $stock     = $this->stockService->findOrCreate($tenantId, $productId, $variantId);
        $paginator = $this->inventoryService->movementHistory($stock, (int) $request->query('per_page', 20));

        return response()->json($paginator);
    }

    // POST /api/inventory/stock/{productId}/move-in
    public function moveIn(MoveStockRequest $request, string $productId): JsonResponse
    {
        $data     = $request->validated();
        $tenantId = $request->user()->tenant_id;
        $stock    = $this->stockService->findOrCreate($tenantId, $productId, $data['variant_id'] ?? null);

        $movement = $this->stockService->moveIn(
            $stock,
            $data['quantity'],
            $data['reason'],
            $data['reference'] ?? null,
            $data['note'] ?? null,
            $request->user()->id,
        );

        return response()->json(['movement' => $movement, 'stock' => $stock->fresh()], 201);
    }

    // POST /api/inventory/stock/{productId}/move-out
    public function moveOut(MoveStockRequest $request, string $productId): JsonResponse
    {
        $data     = $request->validated();
        $tenantId = $request->user()->tenant_id;
        $stock    = $this->stockService->findOrCreate($tenantId, $productId, $data['variant_id'] ?? null);

        try {
            $movement = $this->stockService->moveOut(
                $stock,
                $data['quantity'],
                $data['reason'],
                $data['reference'] ?? null,
                $data['note'] ?? null,
                $request->user()->id,
            );
        } catch (InsufficientStockException $e) {
            return response()->json(['message' => $e->getMessage(), 'available' => $e->available], 422);
        } catch (StockLockException $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        }

        return response()->json(['movement' => $movement, 'stock' => $stock->fresh()], 201);
    }

    // POST /api/inventory/stock/{productId}/adjust
    public function adjust(AdjustStockRequest $request, string $productId): JsonResponse
    {
        $data     = $request->validated();
        $tenantId = $request->user()->tenant_id;
        $stock    = $this->stockService->findOrCreate($tenantId, $productId, $data['variant_id'] ?? null);

        try {
            $movement = $this->stockService->adjust(
                $stock,
                $data['quantity'],
                \App\Modules\Inventory\Models\StockMovement::REASON_COUNT,
                $data['note'] ?? null,
                $request->user()->id,
            );
        } catch (StockLockException $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        }

        return response()->json(['movement' => $movement, 'stock' => $stock->fresh()]);
    }

    // POST /api/inventory/scan
    public function scan(ScanActionRequest $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        try {
            $stock = $this->stockService->findBySku($request->sku, $tenantId);
        } catch (ProductNotFoundException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }

        $stock->load(['product', 'variant']);

        if ($request->action === 'check') {
            return response()->json([
                'sku'          => $request->sku,
                'stock'        => $stock,
                'available'    => $stock->available(),
                'is_low_stock' => $stock->isLowStock(),
            ]);
        }

        try {
            $movement = $request->action === 'move_in'
                ? $this->stockService->moveIn($stock, $request->quantity, $request->reason ?? $request->defaultReason(), $request->reference, null, $request->user()->id)
                : $this->stockService->moveOut($stock, $request->quantity, $request->reason ?? $request->defaultReason(), $request->reference, null, $request->user()->id);
        } catch (InsufficientStockException $e) {
            return response()->json(['message' => $e->getMessage(), 'available' => $e->available], 422);
        } catch (StockLockException $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        }

        return response()->json([
            'movement' => $movement,
            'stock'    => $stock->fresh()->load(['product', 'variant']),
        ], 201);
    }

    // POST /api/inventory/deliveries
    public function receiveDelivery(BatchDeliveryRequest $request): JsonResponse
    {
        $items = $request->validated()['items'];

        // Apply top-level reference to each item if not individually set
        if ($reference = $request->validated()['reference'] ?? null) {
            $items = array_map(fn($i) => array_merge(['reference' => $reference], $i), $items);
        }

        $movements = $this->inventoryService->receiveDelivery(
            $items,
            $request->user()->tenant_id,
            $request->user()->id,
        );

        return response()->json(['movements' => $movements, 'count' => count($movements)], 201);
    }

    // POST /api/inventory/count
    public function inventoryCount(BatchDeliveryRequest $request): JsonResponse
    {
        $items = array_map(fn($i) => array_merge($i, ['counted_quantity' => $i['quantity']]), $request->validated()['items']);

        $movements = $this->inventoryService->processCount(
            $items,
            $request->user()->tenant_id,
            $request->user()->id,
        );

        return response()->json(['movements' => $movements, 'count' => count($movements)]);
    }

    // GET /api/inventory/alerts
    public function alerts(Request $request): JsonResponse
    {
        $items = $this->stockService->lowStockItems($request->user()->tenant_id);

        return response()->json($items);
    }

    /**
     * GET /api/inventory/warehouses/{id}/summary
     * Returns KPI aggregates for a specific warehouse:
     *   sku_count, total_qty, available_qty, low_stock_count, total_value_cents
     */
    public function warehouseSummary(Request $request, string $id): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        // Sprint 11: verify warehouse belongs to tenant (prevents UUID oracle)
        \App\Modules\Inventory\Models\Warehouse::where('tenant_id', $tenantId)
            ->where('id', $id)->firstOrFail();

        $row = DB::table('stocks')
            ->where('tenant_id',   $tenantId)
            ->where('warehouse_id', $id)
            ->selectRaw('
                COUNT(*) as sku_count,
                COALESCE(SUM(quantity), 0) as total_qty,
                COALESCE(SUM(quantity - reserved_quantity), 0) as available_qty,
                COALESCE(SUM(CASE WHEN quantity <= low_stock_threshold AND low_stock_threshold > 0 THEN 1 ELSE 0 END), 0) as low_stock_count,
                COALESCE(SUM(total_value_cents), 0) as total_value_cents
            ')
            ->first();

        return response()->json([
            'data' => [
                'warehouse_id'      => $id,
                'sku_count'         => (int) ($row->sku_count         ?? 0),
                'total_qty'         => (int) ($row->total_qty          ?? 0),
                'available_qty'     => (int) ($row->available_qty      ?? 0),
                'low_stock_count'   => (int) ($row->low_stock_count    ?? 0),
                'total_value_cents' => (int) ($row->total_value_cents  ?? 0),
            ],
        ]);
    }
}
