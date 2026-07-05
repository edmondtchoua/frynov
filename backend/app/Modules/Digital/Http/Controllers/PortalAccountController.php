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

        $email   = strtolower(trim($data['email']));
        $generic = ['message' => 'Si cet email est éligible, un code de vérification vient d\'être envoyé.'];

        // Compte déjà vérifié → réponse générique (pas de divulgation d'existence).
        $existing = PortalAccount::where('email', $email)->first();
        if ($existing && $existing->isVerified()) {
            return response()->json($generic);
        }

        // Anti-bombardement : si un code non expiré est encore valide, on met à jour le mot de passe
        // (dernier inscrit) mais on NE renvoie PAS un nouveau code — évite de spammer l'email de la
        // victime et de drainer les crédits de communication du vendeur (recette QA — SEC-3).
        if ($existing && $existing->hasPendingCode()) {
            $existing->update(['password' => $data['password']]);

            return response()->json($generic);
        }

        $code = (string) random_int(100000, 999999);

        PortalAccount::updateOrCreate(
            ['email' => $email],
            [
                'password'                => $data['password'],
                'verification_code'       => $code,
                'verification_expires_at' => now()->addMinutes(30),
                'verification_attempts'   => 0,
                'verified_at'             => null,
            ],
        );

        // Le code part via le canal d'un vendeur connaissant cet email (comme « mes achats »). On
        // itère les tenants jusqu'à un envoi RÉELLEMENT déposé (notify() est best-effort et peut
        // retourner null si le vendeur n'a pas de canal/modèle) — recette QA (AR-6).
        $tenantIds = Customer::withoutTenantScope()->where('email', $email)
            ->orderBy('tenant_id')->pluck('tenant_id')->unique();
        foreach ($tenantIds as $tenantId) {
            if ($this->notifications->notify($tenantId, 'portal.verify_code', $email, ['code' => $code]) !== null) {
                break;
            }
        }

        return response()->json($generic);
    }

    /** POST /api/portal/verify — {email, code, password} → active le compte. */
    public function verify(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email'    => ['required', 'email', 'max:190'],
            'code'     => ['required', 'string', 'max:10'],
            // Recette QA (AR-1) : la vérification exige AUSSI le mot de passe, liant l'activation à
            // qui l'a défini — un tiers qui aurait fait re-`register` sur l'email ne peut pas activer.
            'password' => ['required', 'string', 'max:100'],
        ]);

        $account = PortalAccount::where('email', strtolower(trim($data['email'])))->first();
        $invalid = fn () => response()->json(['message' => 'Code invalide ou expiré.'], 422);

        if (! $account
            || $account->verification_code === null
            || ! $account->verification_expires_at?->isFuture()
            || $account->verification_attempts >= PortalAccount::MAX_VERIFY_ATTEMPTS) {
            return $invalid();
        }

        $codeOk     = hash_equals((string) $account->verification_code, (string) $data['code']);
        $passwordOk = Hash::check($data['password'], $account->password);

        if (! $codeOk || ! $passwordOk) {
            $account->increment('verification_attempts');
            // Seuil atteint → on brûle le code (force une nouvelle inscription), anti brute-force.
            if ($account->verification_attempts >= PortalAccount::MAX_VERIFY_ATTEMPTS) {
                $account->update(['verification_code' => null, 'verification_expires_at' => null]);
            }

            return $invalid();
        }

        $account->update([
            'verified_at'             => now(),
            'verification_code'       => null,
            'verification_expires_at' => null,
            'verification_attempts'   => 0,
        ]);

        return response()->json(['message' => 'Compte vérifié — vous pouvez vous connecter.']);
    }

    /** POST /api/portal/login — {email, password} → token Sanctum portail (ability `portal`). */
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
            // Ability `portal` : le token ne peut servir QUE le portail (défense complémentaire au
            // GuardPortalPrincipal global). Expiration explicite 30 j (recette QA F-1) — plus de token
            // portail à vie ; révocable côté serveur via /logout (F-2).
            'token' => $account->createToken('portal', ['portal'], now()->addDays(30))->plainTextToken,
            'email' => $account->email,
        ]);
    }

    /** POST /api/portal/logout — révoque le token courant côté serveur (F-2). */
    public function logout(Request $request): JsonResponse
    {
        $account = $request->user();
        if ($account instanceof PortalAccount) {
            $account->currentAccessToken()?->delete();
        }

        return response()->json(['message' => 'Déconnecté.']);
    }

    /** GET /api/portal/my-purchases — tous les achats digitaux actifs de l'email connecté. */
    public function myPurchases(Request $request): JsonResponse
    {
        $account = $request->user();
        // Défense en profondeur : seul un principal portail liste des achats (jamais un user tenant).
        if (! $account instanceof PortalAccount) {
            return response()->json(['message' => 'Jeton non autorisé.'], 403);
        }

        $customerIdsByTenant = Customer::withoutTenantScope()
            ->where('email', $account->email)
            ->get(['id', 'tenant_id']);

        $purchases = collect();
        foreach ($customerIdsByTenant as $customer) {
            $entitlements = DigitalEntitlement::withoutTenantScope()
                ->where('tenant_id', $customer->tenant_id)
                ->where('customer_id', $customer->id)
                ->where('status', DigitalEntitlement::STATUS_ACTIVE)
                // Exclut les accès expirés par date (cohérent avec isAccessible) — recette QA (AR).
                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                // Le principal portail n'a pas de tenant : le TenantScope (fail-closed) masquerait le
                // produit rattaché → on charge la relation SANS le scope (l'entitlement est déjà borné
                // par tenant_id + customer_id).
                ->with(['product' => fn ($q) => $q->withoutGlobalScopes()->select('id', 'name')])
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
