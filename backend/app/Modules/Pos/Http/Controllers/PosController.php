<?php

namespace App\Modules\Pos\Http\Controllers;

use App\Modules\Inventory\Exceptions\InsufficientStockException;
use App\Modules\Payments\Http\Resources\PaymentResource;
use App\Modules\Pos\Http\Resources\CashRegisterSessionResource;
use App\Modules\Pos\Models\CashRegisterSession;
use App\Modules\Pos\Services\PosService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;

class PosController extends Controller
{
    /** Roles allowed to operate the till. */
    private const POS_ROLES = ['admin', 'manager', 'cashier'];

    public function __construct(private readonly PosService $service) {}

    // ── GET /api/pos/sessions ─────────────────────────────────────────────────

    public function index(Request $request): AnonymousResourceCollection|JsonResponse
    {
        if ($denied = $this->guard($request)) {
            return $denied;
        }

        $sessions = CashRegisterSession::query()
            ->latest('opened_at')
            ->paginate((int) $request->integer('per_page', 20));

        return CashRegisterSessionResource::collection($sessions);
    }

    // ── GET /api/pos/sessions/current ─────────────────────────────────────────

    public function current(Request $request): JsonResponse
    {
        if ($denied = $this->guard($request)) {
            return $denied;
        }

        $session = $this->service->currentSession($request->user()->tenant_id, $request->user()->id);

        return response()->json([
            'data' => $session ? new CashRegisterSessionResource($session) : null,
        ]);
    }

    // ── POST /api/pos/sessions ────────────────────────────────────────────────

    public function open(Request $request): JsonResponse
    {
        if ($denied = $this->guard($request)) {
            return $denied;
        }

        $data = $request->validate([
            'opening_float_cents' => ['nullable', 'integer', 'min:0'],
            'warehouse_id'        => ['nullable', 'uuid'],
            'label'               => ['nullable', 'string', 'max:100'],
        ]);

        $session = $this->service->openSession($data, $request->user()->tenant_id, $request->user()->id);

        return response()->json(['data' => new CashRegisterSessionResource($session)], 201);
    }

    // ── POST /api/pos/sessions/{id}/checkout ──────────────────────────────────

    public function checkout(Request $request, string $id): JsonResponse
    {
        if ($denied = $this->guard($request)) {
            return $denied;
        }

        $session = CashRegisterSession::find($id);
        if (! $session) {
            return response()->json(['message' => 'Session de caisse introuvable.'], 404);
        }

        $data = $request->validate([
            'items'              => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'uuid'],
            'items.*.variant_id' => ['nullable', 'uuid'],
            'items.*.quantity'   => ['required', 'integer', 'min:1'],
            'customer_id'        => ['nullable', 'uuid'],
            'method'             => ['nullable', 'in:cash,mobile_money,card,transfer,cheque'],
            'reference'          => ['nullable', 'string', 'max:100'],
            'note'               => ['nullable', 'string'],
        ]);

        try {
            $result = $this->service->checkout($session, $data, $request->user()->tenant_id, $request->user()->id);
        } catch (InsufficientStockException $e) {
            return response()->json(['message' => 'Stock insuffisant pour finaliser la vente.'], 422);
        }

        return response()->json([
            'data' => [
                'order'   => $result['order'],   // Order model (lines loaded) — Orders module serializes models directly
                'payment' => new PaymentResource($result['payment']),
                'session' => new CashRegisterSessionResource($session->fresh()),
            ],
        ], 201);
    }

    // ── POST /api/pos/sessions/{id}/close ─────────────────────────────────────

    public function close(Request $request, string $id): JsonResponse
    {
        if ($denied = $this->guard($request)) {
            return $denied;
        }

        $session = CashRegisterSession::find($id);
        if (! $session) {
            return response()->json(['message' => 'Session de caisse introuvable.'], 404);
        }

        $data = $request->validate([
            'counted_cash_cents' => ['nullable', 'integer', 'min:0'],
            'notes'              => ['nullable', 'string'],
        ]);

        $session = $this->service->closeSession($session, $data, $request->user()->tenant_id, $request->user()->id);

        return response()->json(['data' => new CashRegisterSessionResource($session)]);
    }

    // ── Authorization ─────────────────────────────────────────────────────────

    /** Returns a 403 response when the user may not operate the till, else null. */
    private function guard(Request $request): ?JsonResponse
    {
        if (! $request->user()->hasAnyRole(self::POS_ROLES)) {
            return response()->json(['message' => 'Accès réservé aux caissiers, managers et administrateurs.'], 403);
        }

        return null;
    }
}
