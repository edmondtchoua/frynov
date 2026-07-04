<?php

namespace App\Modules\Orders\Http\Controllers;

use App\Modules\Inventory\Exceptions\InsufficientStockException;
use App\Modules\Inventory\Exceptions\InsufficientUnitsException;
use App\Modules\Inventory\Exceptions\StockLockException;
use App\Modules\Inventory\Models\InventoryUnit;
use App\Modules\Inventory\Services\SerializedAllocationService;
use App\Modules\Orders\Exceptions\OrderNotFoundException;
use App\Modules\Orders\Exceptions\OrderStateException;
use App\Modules\Inventory\Support\WarehouseScope;
use App\Modules\Orders\Http\Requests\CreateOrderRequest;
use App\Modules\Orders\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class OrderController extends Controller
{
    public function __construct(
        private readonly OrderService $orderService,
        private readonly SerializedAllocationService $allocation,
    ) {}

    // GET /api/orders
    public function index(Request $request): JsonResponse
    {
        $paginator = $this->orderService->paginate(
            $request->user()->tenant_id,
            (int) $request->query('per_page', 20),
            $request->query('status'),
            WarehouseScope::resolve($request->user(), $request->query('warehouse_id')),
        );

        return response()->json($paginator);
    }

    // GET /api/orders/{id}
    public function show(Request $request, string $id): JsonResponse
    {
        try {
            $order = $this->orderService->findById(
                $id,
                $request->user()->tenant_id,
                WarehouseScope::resolve($request->user(), null),
            );
        } catch (OrderNotFoundException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }

        return response()->json($order);
    }

    // POST /api/orders
    public function store(CreateOrderRequest $request): JsonResponse
    {
        $order = $this->orderService->create(
            $request->validated(),
            $request->user()->tenant_id,
            $request->user()->id,
        );

        return response()->json($order, 201);
    }

    // POST /api/orders/{id}/confirm
    public function confirm(Request $request, string $id): JsonResponse
    {
        try {
            $order = $this->orderService->findById($id, $request->user()->tenant_id, WarehouseScope::resolve($request->user(), null));
            $order = $this->orderService->confirm($order, $request->user()->id);
        } catch (OrderNotFoundException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (OrderStateException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (InsufficientUnitsException $e) {
            // RC-5C — unités sérialisées disponibles insuffisantes pour une ligne.
            return response()->json(['message' => $e->getMessage(), 'available' => $e->available], 422);
        } catch (InsufficientStockException $e) {
            return response()->json(['message' => $e->getMessage(), 'available' => $e->available], 422);
        } catch (StockLockException $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        }

        return response()->json($order);
    }

    // GET /api/orders/{id}/units — unités sérialisées (IMEI/VIN) rattachées à la commande (RC-5C)
    public function units(Request $request, string $id): JsonResponse
    {
        try {
            $order = $this->orderService->findById($id, $request->user()->tenant_id, WarehouseScope::resolve($request->user(), null));
        } catch (OrderNotFoundException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }

        $units = $this->allocation->forOrder($order->tenant_id, $order->id)
            ->map(fn (InventoryUnit $u) => array_merge($u->toApiArray(), [
                'order_line_id' => $u->order_line_id,
                'customer_id'   => $u->customer_id,
            ]));

        return response()->json(['data' => $units, 'count' => $units->count()]);
    }

    // POST /api/orders/{id}/fulfill
    public function fulfill(Request $request, string $id): JsonResponse
    {
        try {
            $order = $this->orderService->findById($id, $request->user()->tenant_id, WarehouseScope::resolve($request->user(), null));
            $order = $this->orderService->fulfill($order, $request->user()->id);
        } catch (OrderNotFoundException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (OrderStateException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (InsufficientStockException $e) {
            return response()->json(['message' => $e->getMessage(), 'available' => $e->available], 422);
        } catch (\App\Modules\Digital\Exceptions\LicensePoolExhaustedException $e) {
            // RC-6E — politique `block` : pas de clé importée → la livraison attend un réassort du pool.
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (StockLockException $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        }

        return response()->json($order);
    }

    // POST /api/orders/{id}/cancel
    public function cancel(Request $request, string $id): JsonResponse
    {
        try {
            $order = $this->orderService->findById($id, $request->user()->tenant_id, WarehouseScope::resolve($request->user(), null));
            $order = $this->orderService->cancel($order, $request->user()->id);
        } catch (OrderNotFoundException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (OrderStateException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($order);
    }
}
