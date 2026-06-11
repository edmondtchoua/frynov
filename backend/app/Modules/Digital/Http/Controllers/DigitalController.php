<?php

namespace App\Modules\Digital\Http\Controllers;

use App\Modules\Digital\Models\DigitalEntitlement;
use App\Modules\Digital\Services\DigitalService;
use App\Modules\Orders\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * RC-5E — consultation des droits d'accès digitaux, vérification d'accès par jeton, révocation.
 */
class DigitalController extends Controller
{
    public function __construct(private readonly DigitalService $digital) {}

    /** GET /api/digital/orders/{orderId}/entitlements — accès générés pour une commande (sans secret). */
    public function forOrder(Request $request, string $orderId): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $order = Order::where('tenant_id', $tenantId)->where('id', $orderId)->first();
        if (! $order) {
            return response()->json(['message' => 'Commande introuvable.'], 404);
        }

        $entitlements = $this->digital->forOrder($tenantId, $orderId)
            ->map(fn (DigitalEntitlement $e) => array_merge($e->toApiArray(), [
                'product_name' => $e->product?->name,
            ]));

        return response()->json(['data' => $entitlements, 'count' => $entitlements->count()]);
    }

    /**
     * GET /api/digital/access/{token} — vérifie un droit d'accès et révèle le secret (clé / lien) si
     * l'accès est actif. Refuse (403) si révoqué ou expiré. C'est le point qui REMPLACE l'exposition
     * directe d'un fichier : aucun chemin privé n'est jamais renvoyé.
     */
    public function access(Request $request, string $token): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $entitlement = $this->digital->findByToken($tenantId, $token);
        if (! $entitlement) {
            return response()->json(['message' => 'Accès introuvable.'], 404);
        }

        if (! $entitlement->isAccessible()) {
            return response()->json(['message' => 'Accès révoqué ou expiré.', 'status' => $entitlement->status], 403);
        }

        // Accès accordé : on révèle le secret (clé de licence et/ou lien de téléchargement signé — à
        // brancher sur un stockage privé dans un incrément ultérieur).
        return response()->json(['data' => $entitlement->toApiArray(withSecret: true)]);
    }

    /** POST /api/digital/entitlements/{id}/revoke — révoque un accès (manager/admin). */
    public function revoke(Request $request, string $id): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $entitlement = DigitalEntitlement::where('tenant_id', $tenantId)->where('id', $id)->first();
        if (! $entitlement) {
            return response()->json(['message' => 'Accès introuvable.'], 404);
        }

        $this->digital->revoke($entitlement, $request->user()->id);

        return response()->json(['data' => $entitlement->fresh()->toApiArray()]);
    }
}
