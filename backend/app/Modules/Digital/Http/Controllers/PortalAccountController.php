<?php

namespace App\Modules\Digital\Http\Controllers;

use App\Modules\Customers\Models\Customer;
use App\Modules\Digital\Models\DigitalEntitlement;
use App\Modules\Digital\Models\PortalAccount;
use App\Modules\Digital\Services\DigitalService;
use App\Modules\Notifications\Services\NotificationService;
use App\Modules\Tenants\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;

/**
 * RC-7C — comptes clients du portail (3ᵉ mode d'accès). Sécurité :
 *  - inscription → l'email doit être VÉRIFIÉ par code (envoyé via le canal d'un vendeur connaissant
 *    l'email) avant tout login — on ne revendique pas l'email d'autrui ;
 *  - réponses génériques (anti-énumération) ; endpoints publics throttlés côté routes ;
 *  - le token émis est un token Sanctum du modèle PortalAccount (jamais un user tenant).
 */
class PortalAccountController extends Controller
{
    public function __construct(
        private readonly DigitalService $digital,
        private readonly NotificationService $notifications,
    ) {}

    /** POST /api/portal/register — {email, password} → compte non vérifié + code envoyé. */
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email'    => ['required', 'email', 'max:190'],
            'password' => ['required', 'string', 'min:8', 'max:100'],
        ]);

        $email = strtolower(trim($data['email']));

        // Compte déjà vérifié → réponse générique (pas de divulgation d'existence).
        $existing = PortalAccount::where('email', $email)->first();
        if ($existing && $existing->isVerified()) {
            return response()->json(['message' => 'Si cet email est éligible, un code de vérification vient d\'être envoyé.']);
        }

        $code = (string) random_int(100000, 999999);

        PortalAccount::updateOrCreate(
            ['email' => $email],
            [
                'password'                => $data['password'],
                'verification_code'       => $code,
                'verification_expires_at' => now()->addMinutes(30),
                'verified_at'             => null,
            ],
        );

        // Le code part via le canal du/des vendeur(s) connaissant cet email (comme « mes achats »).
        Customer::withoutTenantScope()->where('email', $email)->get()
            ->unique('tenant_id')
            ->take(1) // un seul envoi suffit
            ->each(fn (Customer $c) => $this->notifications->notify($c->tenant_id, 'portal.verify_code', $email, ['code' => $code]));

        return response()->json(['message' => 'Si cet email est éligible, un code de vérification vient d\'être envoyé.']);
    }

    /** POST /api/portal/verify — {email, code} → active le compte. */
    public function verify(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:190'],
            'code'  => ['required', 'string', 'max:10'],
        ]);

        $account = PortalAccount::where('email', strtolower(trim($data['email'])))->first();

        if (! $account
            || $account->verification_code !== $data['code']
            || ! $account->verification_expires_at?->isFuture()) {
            return response()->json(['message' => 'Code invalide ou expiré.'], 422);
        }

        $account->update([
            'verified_at'             => now(),
            'verification_code'       => null,
            'verification_expires_at' => null,
        ]);

        return response()->json(['message' => 'Compte vérifié — vous pouvez vous connecter.']);
    }

    /** POST /api/portal/login — {email, password} → token Sanctum portail. */
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email'    => ['required', 'email', 'max:190'],
            'password' => ['required', 'string', 'max:100'],
        ]);

        $account = PortalAccount::where('email', strtolower(trim($data['email'])))->first();

        if (! $account || ! $account->isVerified() || ! Hash::check($data['password'], $account->password)) {
            return response()->json(['message' => 'Identifiants invalides.'], 422);
        }

        $account->update(['last_login_at' => now()]);

        return response()->json([
            'token' => $account->createToken('portal')->plainTextToken,
            'email' => $account->email,
        ]);
    }

    /** GET /api/portal/my-purchases — tous les achats digitaux actifs de l'email connecté. */
    public function myPurchases(Request $request): JsonResponse
    {
        /** @var PortalAccount $account */
        $account = $request->user();

        $customerIdsByTenant = Customer::withoutTenantScope()
            ->where('email', $account->email)
            ->get(['id', 'tenant_id']);

        $purchases = collect();
        foreach ($customerIdsByTenant as $customer) {
            $entitlements = DigitalEntitlement::withoutTenantScope()
                ->where('tenant_id', $customer->tenant_id)
                ->where('customer_id', $customer->id)
                ->where('status', DigitalEntitlement::STATUS_ACTIVE)
                ->with('product:id,name')
                ->get();

            $tenant = Tenant::withoutGlobalScopes()->find($customer->tenant_id);

            foreach ($entitlements as $e) {
                $purchases->push([
                    'product_name' => $e->product?->name,
                    'seller_name'  => $tenant?->name,
                    'granted_at'   => $e->granted_at?->toISOString(),
                    'portal_link'  => $this->digital->portalLink($e), // ouvre l'accès complet (clé + fichiers)
                ]);
            }
        }

        return response()->json(['data' => $purchases->values(), 'count' => $purchases->count()]);
    }
}
