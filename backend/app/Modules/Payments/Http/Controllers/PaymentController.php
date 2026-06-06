<?php

namespace App\Modules\Payments\Http\Controllers;

use App\Modules\Inventory\Support\WarehouseScope;
use App\Modules\Orders\Models\Order;
use App\Modules\Payments\Http\Resources\PaymentResource;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Services\PaymentService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;

class PaymentController extends Controller
{
    public function __construct(private readonly PaymentService $service) {}

    // ── GET /api/payments ─────────────────────────────────────────────────────

    public function index(Request $request): AnonymousResourceCollection
    {
        $payments = $this->service->list(
            tenantId: $request->user()->tenant_id,
            filters:  array_merge(
                $request->only(['order_id', 'method', 'from', 'to', 'per_page']),
                ['warehouse_ids' => WarehouseScope::resolve($request->user(), $request->query('warehouse_id'))],
            ),
        );

        return PaymentResource::collection($payments);
    }

    // ── POST /api/payments ────────────────────────────────────────────────────

    public function store(Request $request): JsonResponse
    {
        $idempotencyKey = $request->header('X-Idempotency-Key');

        if ($idempotencyKey) {
            $existing = Payment::where('tenant_id', $request->user()->tenant_id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing) {
                $existing->load('order');
                return response()->json(['data' => new PaymentResource($existing)]);
            }
        }

        $data = $request->validate([
            'order_id'     => ['nullable', 'uuid'],
            'amount_cents' => ['required', 'integer', 'min:1'],
            'currency'     => ['required', 'string', 'size:3'],
            'method'       => ['required', 'in:cash,mobile_money,card,transfer,cheque'],
            'reference'    => ['nullable', 'string', 'max:100'],
            'note'         => ['nullable', 'string'],
            'paid_at'      => ['nullable', 'date'],
        ]);

        // Verify order belongs to tenant when provided
        if (! empty($data['order_id'])) {
            $order = Order::where('tenant_id', $request->user()->tenant_id)
                ->find($data['order_id']);

            if (! $order) {
                return response()->json(['message' => 'Commande introuvable.'], 404);
            }
        }

        $payment = $this->service->record(
            $data + ['idempotency_key' => $idempotencyKey],
            $request->user()->tenant_id,
            $request->user()->id,
        );
        $payment->load('order');

        // Append balance info when linked to an order
        $response = ['data' => new PaymentResource($payment)];
        if (isset($order)) {
            $response['balance']      = $this->service->balance($order);
            $response['is_fully_paid'] = $this->service->isFullyPaid($order);
        }

        return response()->json($response, 201);
    }

    // ── GET /api/payments/{id} ────────────────────────────────────────────────

    public function show(Request $request, string $id): JsonResponse
    {
        try {
            $payment = $this->service->findOrFail($id, $request->user()->tenant_id);
        } catch (ModelNotFoundException) {
            return response()->json(['message' => 'Paiement introuvable.'], 404);
        }

        $payment->load('order');

        return response()->json(['data' => new PaymentResource($payment)]);
    }

    // ── DELETE /api/payments/{id} (void) ──────────────────────────────────────

    public function destroy(Request $request, string $id): JsonResponse
    {
        // Sprint 11: only admin/manager can void payments — viewers/members must not
        if (!$request->user()->hasAnyRole(['admin', 'manager'])) {
            return response()->json(['message' => 'Action réservée aux administrateurs et managers.'], 403);
        }

        try {
            $payment = $this->service->findOrFail($id, $request->user()->tenant_id);
        } catch (ModelNotFoundException) {
            return response()->json(['message' => 'Paiement introuvable.'], 404);
        }

        $this->service->void($payment);

        return response()->json(null, 204);
    }

    // ── GET /api/orders/{orderId}/payments ────────────────────────────────────

    public function forOrder(Request $request, string $orderId): JsonResponse
    {
        try {
            $order = Order::where('tenant_id', $request->user()->tenant_id)->findOrFail($orderId);
        } catch (ModelNotFoundException) {
            return response()->json(['message' => 'Commande introuvable.'], 404);
        }

        $payments = $this->service->listForOrder($order);

        return response()->json([
            'data'          => PaymentResource::collection($payments),
            'balance'       => $this->service->balance($order),
            'total_amount'  => $order->total_amount,
            'is_fully_paid' => $this->service->isFullyPaid($order),
        ]);
    }
}
