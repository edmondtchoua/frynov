<?php

namespace App\Modules\Delivery\Http\Controllers;

use App\Modules\Delivery\Http\Resources\DeliveryResource;
use App\Modules\Delivery\Services\DeliveryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;

class DeliveryController extends Controller
{
    public function __construct(private readonly DeliveryService $service) {}

    // ── GET /api/deliveries ───────────────────────────────────────────────────

    public function index(Request $request): AnonymousResourceCollection
    {
        return DeliveryResource::collection(
            $this->service->list($request->user()->tenant_id, $request->query())
        );
    }

    // ── POST /api/deliveries ──────────────────────────────────────────────────

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'order_id'        => 'nullable|uuid',
            'address'         => 'nullable|array',
            'address.street'  => 'nullable|string|max:255',
            'address.city'    => 'nullable|string|max:100',
            'address.zip'     => 'nullable|string|max:20',
            'address.country' => 'nullable|string|max:100',
            'carrier'         => 'nullable|string|max:100',
            'tracking_number' => 'nullable|string|max:100',
            'notes'           => 'nullable|string',
        ]);

        // Verify order belongs to this tenant
        if (!empty($data['order_id'])) {
            $exists = \App\Modules\Orders\Models\Order::where('id', $data['order_id'])
                ->where('tenant_id', $request->user()->tenant_id)
                ->exists();

            if (!$exists) {
                return response()->json(['message' => 'Order not found.'], 404);
            }
        }

        $delivery = $this->service->create($data, $request->user()->tenant_id, $request->user()->id);

        return (new DeliveryResource($delivery->load('order')))
            ->response()
            ->setStatusCode(201);
    }

    // ── GET /api/deliveries/{id} ──────────────────────────────────────────────

    public function show(Request $request, string $id): DeliveryResource
    {
        return new DeliveryResource(
            $this->service->findOrFail($id, $request->user()->tenant_id)
        );
    }

    // ── POST /api/deliveries/{id}/dispatch ────────────────────────────────────

    public function dispatch(Request $request, string $id): DeliveryResource|JsonResponse
    {
        $delivery = $this->service->findOrFail($id, $request->user()->tenant_id);
        try {
            return new DeliveryResource($this->service->dispatch($delivery));
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    // ── POST /api/deliveries/{id}/deliver ─────────────────────────────────────

    public function deliver(Request $request, string $id): DeliveryResource|JsonResponse
    {
        $delivery = $this->service->findOrFail($id, $request->user()->tenant_id);
        try {
            return new DeliveryResource($this->service->confirm($delivery));
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    // ── POST /api/deliveries/{id}/fail ────────────────────────────────────────

    public function fail(Request $request, string $id): DeliveryResource|JsonResponse
    {
        $data = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $delivery = $this->service->findOrFail($id, $request->user()->tenant_id);
        try {
            return new DeliveryResource($this->service->fail($delivery, $data['reason']));
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    // ── GET /api/orders/{orderId}/deliveries ──────────────────────────────────

    public function forOrder(Request $request, string $orderId): JsonResponse
    {
        $deliveries = $this->service->listForOrder($orderId, $request->user()->tenant_id);

        return response()->json([
            'data' => DeliveryResource::collection($deliveries),
        ]);
    }
}
