<?php

namespace App\Modules\Digital\Http\Controllers;

use App\Modules\Customers\Models\Customer;
use App\Modules\Digital\Models\DigitalEntitlement;
use App\Modules\Digital\Services\DigitalAssetService;
use App\Modules\Digital\Services\DigitalService;
use App\Modules\Notifications\Services\NotificationService;
use App\Modules\Tenants\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * RC-6C — PORTAIL CLIENT digital (public, throttlé). Trois modes d'accès (arbitrage fondateur) :
 *   1. **Saisie de jeton** : le client colle son jeton d'accès → détail de l'achat + liens signés.
 *   2. **Lien magique** : l'email de livraison contient l'URL `/portal?token=…` (même endpoint).
 *   3. **« Mes achats » par email** : le client saisit son email → il reçoit (par le canal du/des
 *      tenant(s) concerné(s)) la liste de ses achats actifs avec leurs liens magiques. Réponse
 *      TOUJOURS 200 (aucune divulgation d'existence d'email).
 *
 * Aucune donnée n'est renvoyée sans jeton valide ; la révocation prime (403).
 */
class PortalController extends Controller
{
    public function __construct(
        private readonly DigitalService $digital,
        private readonly DigitalAssetService $assets,
        private readonly NotificationService $notifications,
    ) {}

    /** POST /api/portal/digital/access — {token} → détail de l'achat + secrets + liens signés. */
    public function access(Request $request): JsonResponse
    {
        $data = $request->validate(['token' => ['required', 'uuid']]);

        $entitlement = $this->digital->findByTokenGlobal($data['token']);
        if (! $entitlement) {
            return response()->json(['message' => 'Accès introuvable.'], 404);
        }
        if (! $entitlement->isAccessible()) {
            return response()->json(['message' => 'Accès révoqué ou expiré.', 'status' => $entitlement->status], 403);
        }

        $tenant = Tenant::withoutGlobalScopes()->find($entitlement->tenant_id);

        return response()->json(['data' => [
            'product_name'     => $entitlement->product?->name,
            'seller_name'      => $tenant?->name,
            'fulfillment_type' => $entitlement->fulfillment_type,
            'granted_at'       => $entitlement->granted_at?->toISOString(),
            'license_key'      => $entitlement->license_key,
            'download_urls'    => $this->assets->signedLinksFor($entitlement),
        ]]);
    }

    /**
     * POST /api/portal/digital/request-links — {email} → envoie les liens magiques de TOUS les achats
     * actifs de ce client (un email par vendeur concerné, via SON canal). Toujours 200.
     */
    public function requestLinks(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:190']]);

        // Le même email peut être client chez plusieurs tenants : un envoi par tenant concerné.
        $customers = Customer::withoutTenantScope()
            ->where('email', $data['email'])
            ->get();

        foreach ($customers as $customer) {
            $entitlements = DigitalEntitlement::withoutTenantScope()
                ->where('tenant_id', $customer->tenant_id)
                ->where('customer_id', $customer->id)
                ->where('status', DigitalEntitlement::STATUS_ACTIVE)
                ->with('product')
                ->get();

            if ($entitlements->isEmpty()) {
                continue;
            }

            $lines = $entitlements->map(fn (DigitalEntitlement $e) => sprintf(
                '- %s : %s',
                $e->product?->name ?? 'Achat digital',
                $this->digital->portalLink($e),
            ))->implode("\n");

            $tenant = Tenant::withoutGlobalScopes()->find($customer->tenant_id);

            $this->notifications->notify($customer->tenant_id, 'digital.portal_links', $data['email'], [
                'customer_name' => $customer->name,
                'tenant_name'   => $tenant?->name ?? '',
                'links'         => $lines,
                'count'         => $entitlements->count(),
            ]);
        }

        // Réponse générique : ne divulgue jamais si l'email est connu.
        return response()->json(['message' => 'Si des achats existent pour cet email, un message vient d\'être envoyé.']);
    }
}
