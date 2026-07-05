<?php

namespace App\Modules\Notifications\Http\Controllers;

use App\Modules\Notifications\Models\CommunicationCreditMovement;
use App\Modules\Notifications\Models\CreditRechargeOrder;
use App\Modules\Notifications\Services\CommunicationCreditService;
use App\Modules\Notifications\Services\RechargeOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

/**
 * RC-7E — crédits de communication du tenant : consultation des soldes / mouvements (ouvert) et
 * recharge d'un pack via le rail de paiement manuel (manager/admin).
 */
class CommunicationCreditController extends Controller
{
    public function __construct(
        private readonly CommunicationCreditService $credits,
        private readonly RechargeOrderService $orders,
    ) {}

    /** GET /api/notifications/credits — soldes par canal + packs disponibles. */
    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $packs = collect($this->credits->packs())
            ->map(fn (array $p, string $code) => array_merge($p, ['code' => $code]))
            ->values();

        return response()->json(['data' => [
            'enabled'  => (bool) config('notifications.credits.enabled', true),
            'balances' => $this->credits->balances($tenantId),
            'metered'  => (array) config('notifications.credits.metered_channels', []),
            'packs'    => $packs,
        ]]);
    }

    /** GET /api/notifications/credits/movements — journal des mouvements (récent d'abord). */
    public function movements(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $channel  = (string) $request->query('channel', '');

        $query = CommunicationCreditMovement::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->when($channel !== '', fn ($q) => $q->where('channel', $channel))
            ->latest()
            ->limit((int) min(200, max(1, (int) $request->query('limit', 50))));

        return response()->json([
            'data' => $query->get()->map(fn (CommunicationCreditMovement $m) => $m->toApiArray()),
        ]);
    }

    /** POST /api/notifications/credits/recharge — applique un pack (paiement encaissé hors ligne). */
    public function recharge(Request $request): JsonResponse
    {
        $data = $request->validate([
            'pack_code'         => ['required', 'string', Rule::in(array_keys($this->credits->packs()))],
            'payment_reference' => ['nullable', 'string', 'max:191'],
        ]);

        $result = $this->credits->recharge(
            $request->user()->tenant_id,
            $data['pack_code'],
            $data['payment_reference'] ?? null,
            $request->user()->id,
        );

        return response()->json(['data' => $result], 201);
    }

    // ── RC-7F — commandes de recharge Mobile Money ──────────────────────────

    /** GET /api/notifications/credits/orders — dernières commandes de recharge du tenant. */
    public function orders(Request $request): JsonResponse
    {
        $rows = CreditRechargeOrder::withoutTenantScope()
            ->where('tenant_id', $request->user()->tenant_id)
            ->latest()
            ->limit((int) min(100, max(1, (int) $request->query('limit', 20))))
            ->get()
            ->map(fn (CreditRechargeOrder $o) => $o->toApiArray());

        return response()->json(['data' => $rows]);
    }

    /** POST /api/notifications/credits/orders — crée une commande payable par Mobile Money. */
    public function storeOrder(Request $request): JsonResponse
    {
        $data = $request->validate([
            'pack_code' => ['required', 'string', Rule::in(array_keys($this->credits->packs()))],
        ]);

        $order = $this->orders->createOrder($request->user()->tenant_id, $data['pack_code'], $request->user()->id);

        return response()->json(['data' => $order->toApiArray()], 201);
    }

    /** POST /api/notifications/credits/orders/{id}/cancel — annule une commande en attente. */
    public function cancelOrder(Request $request, string $id): JsonResponse
    {
        $order = CreditRechargeOrder::withoutTenantScope()
            ->where('tenant_id', $request->user()->tenant_id)
            ->find($id);
        if (! $order) {
            return response()->json(['message' => 'Commande introuvable.'], 404);
        }

        try {
            return response()->json(['data' => $this->orders->cancel($order)->toApiArray()]);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
