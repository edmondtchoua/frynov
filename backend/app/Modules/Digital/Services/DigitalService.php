<?php

namespace App\Modules\Digital\Services;

use App\Modules\Billing\Models\Plan;
use App\Modules\Catalog\Models\Product;
use App\Modules\Digital\Exceptions\LicensePoolExhaustedException;
use App\Modules\Digital\Models\DigitalEntitlement;
use App\Modules\Digital\Models\LicensePoolKey;
use App\Modules\Notifications\Services\NotificationService;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderLine;
use App\Modules\Tenants\Models\Tenant;
use Illuminate\Support\Str;

/**
 * RC-5E — produits digitaux : génération des droits d'accès (entitlements) à la vente.
 *
 * À la livraison (`OrderService::fulfill`), pour chaque ligne dont le produit se livre en
 * `download`/`license`, on accorde au client un droit d'accès porté par un `access_token` opaque
 * (et une clé de licence si `license`). L'accès au contenu se vérifie ensuite serveur, jamais par un
 * chemin de fichier exposé. Idempotent : une ligne déjà dotée d'un accès n'est pas réémise.
 */
class DigitalService
{
    private const DIGITAL_FULFILLMENTS = [Product::FULFILLMENT_DOWNLOAD, Product::FULFILLMENT_LICENSE];

    public function __construct(private readonly NotificationService $notifications) {}

    /**
     * @return array<int,DigitalEntitlement> entitlements créés
     */
    public function issueForOrder(Order $order, ?string $userId = null): array
    {
        $order->loadMissing('lines');
        if ($order->lines->isEmpty()) {
            return [];
        }

        $products = Product::withoutTenantScope()
            ->where('tenant_id', $order->tenant_id)
            ->whereIn('id', $order->lines->pluck('product_id')->unique()->all())
            ->get()
            ->keyBy('id');

        $created = [];

        foreach ($order->lines as $line) {
            $product = $products->get($line->product_id);
            if (! $product || ! in_array($product->fulfillment_type, self::DIGITAL_FULFILLMENTS, true)) {
                continue;
            }

            // RC-7D — un accès PAR EXEMPLAIRE : une ligne de qty N accorde N entitlements
            // (jeton/clé distincts). Idempotence : ne créer que les exemplaires manquants (le
            // nombre déjà émis est le rang du dernier), donc un fulfill rejoué n'ajoute rien.
            $quantity = max(1, (int) $line->quantity);
            $already = DigitalEntitlement::withoutTenantScope()
                ->where('tenant_id', $order->tenant_id)
                ->where('order_line_id', $line->id)
                ->count();
            if ($already >= $quantity) {
                continue;
            }

            for ($unitIndex = $already + 1; $unitIndex <= $quantity; $unitIndex++) {
                $entitlement = $this->grant($order, $line, $product, $userId, $unitIndex);
                $this->notifyDelivery($order, $product, $entitlement); // RC-6A — best-effort, un envoi par exemplaire
                $created[] = $entitlement;
            }
        }

        return $created;
    }

    /** RC-6C — lien magique du portail client pour un accès. */
    public function portalLink(DigitalEntitlement $entitlement): string
    {
        return rtrim((string) config('app.frontend_url'), '/') . '/portal?token=' . $entitlement->access_token;
    }

    /** RC-6A — email de livraison digitale au client (jeton + clé), si son email est connu. */
    private function notifyDelivery(Order $order, Product $product, DigitalEntitlement $entitlement): void
    {
        try {
            $customer = $order->customer_id
                ? \App\Modules\Customers\Models\Customer::withoutTenantScope()
                    ->where('tenant_id', $order->tenant_id)->find($order->customer_id)
                : null;
            $email = (string) ($customer?->email ?? '');
            if ($email === '') {
                return;
            }

            $tenant = \App\Modules\Tenants\Models\Tenant::withoutGlobalScopes()->find($order->tenant_id);

            $this->notifications->notify($order->tenant_id, 'digital.delivery', $email, [
                'customer_name' => $customer?->name ?? '',
                'product_name'  => $product->name,
                'order_number'  => $order->number,
                'access_token'  => $entitlement->access_token,
                'portal_link'   => $this->portalLink($entitlement), // RC-6C — lien magique
                'license_line'  => $entitlement->license_key ? "Clé de licence : {$entitlement->license_key}" : '',
                'tenant_name'   => $tenant?->name ?? '',
            ]);
        } catch (\Throwable) {
            // best-effort : ne bloque jamais le fulfillment
        }
    }

    /** Révoque un accès (le client ne peut plus télécharger/activer). */
    public function revoke(DigitalEntitlement $entitlement, ?string $userId = null): DigitalEntitlement
    {
        $entitlement->update([
            'status'     => DigitalEntitlement::STATUS_REVOKED,
            'revoked_at' => now(),
        ]);

        $this->releasePoolKeys([$entitlement->id]); // RC-18 (D-3)

        return $entitlement;
    }

    /**
     * RC-5H — révoque TOUS les accès digitaux actifs d'une ligne (le client perd tout accès de cette ligne).
     *
     * @return int nombre d'accès révoqués
     */
    public function revokeForOrderLine(string $tenantId, string $orderLineId): int
    {
        $ids = DigitalEntitlement::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->where('order_line_id', $orderLineId)
            ->where('status', DigitalEntitlement::STATUS_ACTIVE)
            ->pluck('id')->all();

        if (empty($ids)) {
            return 0;
        }

        DigitalEntitlement::withoutTenantScope()
            ->whereIn('id', $ids)
            ->update([
                'status'     => DigitalEntitlement::STATUS_REVOKED,
                'revoked_at' => now(),
            ]);

        $this->releasePoolKeys($ids); // RC-18 (D-3)

        return count($ids);
    }

    /**
     * RC-18 (D-3) — libère les clés de pool des accès révoqués : elles redeviennent `available`
     * (FIFO de réassignation). Sans cela le pool fuyait à chaque retour/révocation → épuisement
     * prématuré et fausses alertes `pool_exhausted`. NB : une clé re-poolée provient d'un retour ;
     * si l'éditeur invalide les clés exposées, purger via l'écran du pool.
     */
    private function releasePoolKeys(array $entitlementIds): void
    {
        if (empty($entitlementIds)) {
            return;
        }

        LicensePoolKey::withoutTenantScope()
            ->whereIn('entitlement_id', $entitlementIds)
            ->where('status', LicensePoolKey::STATUS_ASSIGNED)
            ->update([
                'status'         => LicensePoolKey::STATUS_AVAILABLE,
                'entitlement_id' => null,
                'assigned_at'    => null,
            ]);
    }

    /**
     * RC-7D — révocation AU PRORATA : ne conserve que `keepActive` exemplaires actifs pour la ligne,
     * en révoquant les plus RÉCENTS d'abord (rangs les plus élevés) — on garde ainsi les premiers
     * exemplaires, les plus susceptibles d'avoir déjà été activés/téléchargés par le client.
     * Idempotent : rejoué, ne révoque plus rien. Un produit non digital (0 entitlement) est un no-op.
     *
     * @return int nombre d'accès révoqués lors de cet appel
     */
    public function revokeDownToActive(string $tenantId, string $orderLineId, int $keepActive, ?string $userId = null): int
    {
        $active = DigitalEntitlement::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->where('order_line_id', $orderLineId)
            ->where('status', DigitalEntitlement::STATUS_ACTIVE)
            ->orderByDesc('unit_index') // révoque les exemplaires les plus récents d'abord
            ->get();

        $toRevoke = $active->count() - max(0, $keepActive);
        if ($toRevoke <= 0) {
            return 0;
        }

        $ids = $active->take($toRevoke)->pluck('id')->all();
        DigitalEntitlement::withoutTenantScope()
            ->whereIn('id', $ids)
            ->update([
                'status'     => DigitalEntitlement::STATUS_REVOKED,
                'revoked_at' => now(),
            ]);

        $this->releasePoolKeys($ids); // RC-18 (D-3)

        return count($ids);
    }

    /** Retrouve un accès par son jeton opaque (scopé tenant). */
    public function findByToken(string $tenantId, string $token): ?DigitalEntitlement
    {
        return DigitalEntitlement::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->where('access_token', $token)
            ->first();
    }

    /**
     * RC-5I — résout un accès par son seul jeton (globalement unique), sans contexte tenant. Réservé à
     * la route de téléchargement signée (hors auth) ; le tenant est ensuite dérivé de l'entitlement.
     */
    public function findByTokenGlobal(string $token): ?DigitalEntitlement
    {
        return DigitalEntitlement::withoutTenantScope()
            ->where('access_token', $token)
            ->first();
    }

    /** Entitlements rattachés à une commande (traçabilité). */
    public function forOrder(string $tenantId, string $orderId): \Illuminate\Database\Eloquent\Collection
    {
        return DigitalEntitlement::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->where('order_id', $orderId)
            ->orderBy('order_line_id')
            ->orderBy('unit_index')
            ->get();
    }

    private function grant(Order $order, OrderLine $line, Product $product, ?string $userId, int $unitIndex = 1): DigitalEntitlement
    {
        $isLicense = $product->fulfillment_type === Product::FULFILLMENT_LICENSE;

        // RC-6E — clé issue du POOL importé (FIFO) si disponible, sinon politique d'épuisement du plan.
        $poolKey = null;
        if ($isLicense) {
            [$licenseKey, $poolKey] = $this->pullLicenseKey($order->tenant_id, $product);
        }

        $entitlement = DigitalEntitlement::create([
            'tenant_id'        => $order->tenant_id,
            'product_id'       => $product->id,
            'variant_id'       => $line->variant_id,
            'order_id'         => $order->id,
            'order_line_id'    => $line->id,
            'unit_index'       => $unitIndex,
            'customer_id'      => $order->customer_id,
            'fulfillment_type' => $product->fulfillment_type,
            'access_token'     => (string) Str::uuid(),
            'license_key'      => $isLicense ? $licenseKey : null,
            'status'           => DigitalEntitlement::STATUS_ACTIVE,
            'granted_at'       => $order->fulfilled_at ?? now(),
            'created_by'       => $userId,
        ]);

        // Rattacher la clé de pool consommée à son accès (traçabilité éditeur).
        $poolKey?->update([
            'status'         => LicensePoolKey::STATUS_ASSIGNED,
            'entitlement_id' => $entitlement->id,
            'assigned_at'    => now(),
        ]);

        return $entitlement;
    }

    // ── RC-6E — pool de clés éditeur (politiques par plan) ─────────────────

    /**
     * Prend la prochaine clé du pool (FIFO d'import, verrou lecture anti double-assignation).
     * Pool vide → politique d'épuisement : `generate` (repli + alerte) ou `block` (exception).
     *
     * @return array{0:string,1:?LicensePoolKey} [clé, ligne de pool consommée (null si générée)]
     * @throws LicensePoolExhaustedException
     */
    private function pullLicenseKey(string $tenantId, Product $product): array
    {
        $poolKey = LicensePoolKey::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->where('product_id', $product->id)
            ->where('status', LicensePoolKey::STATUS_AVAILABLE)
            ->oldest()
            ->lockForUpdate()
            ->first();

        if ($poolKey) {
            return [$poolKey->license_key, $poolKey];
        }

        // Pool vide — mais n'alerter que s'il a déjà servi (sinon le pool n'est simplement pas utilisé).
        $poolEverUsed = LicensePoolKey::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->where('product_id', $product->id)
            ->exists();

        $behavior = $this->poolExhaustionBehavior($tenantId);

        if ($poolEverUsed) {
            $this->notifyPoolExhausted($tenantId, $product, $behavior);
        }
        if ($behavior === 'block') {
            // Politique stricte : jamais de génération, même sans historique de pool.
            throw new LicensePoolExhaustedException($product->name);
        }

        return [$this->generateLicenseKey(), null];
    }

    /** Politique d'épuisement : surcharge tenant → config par plan → défaut `generate`. */
    public function poolExhaustionBehavior(string $tenantId): string
    {
        $tenant = Tenant::withoutGlobalScopes()->find($tenantId);

        $fromTenant = $tenant->settings['license_pool_exhaustion'] ?? null;
        if (in_array($fromTenant, ['generate', 'block'], true)) {
            return $fromTenant;
        }

        $perPlan = (array) config('digital.pool_exhaustion.per_plan', []);

        return $perPlan[$tenant->plan ?? ''] ?? (string) config('digital.pool_exhaustion.default', 'generate');
    }

    /** Taille maximale d'un import de clés, selon le plan du tenant. */
    public function poolImportLimit(string $tenantId): int
    {
        $tenant  = Tenant::withoutGlobalScopes()->find($tenantId);
        $perPlan = (array) config('digital.pool_import_limits.per_plan', []);

        return (int) ($perPlan[$tenant->plan ?? ''] ?? config('digital.pool_import_limits.default', 100));
    }

    /**
     * Importe des clés (doublons du lot et déjà présents ignorés).
     *
     * @param array<int,string> $keys
     * @return array{imported:int,skipped:int}
     */
    public function importPoolKeys(string $tenantId, Product $product, array $keys, ?string $userId = null): array
    {
        $imported = 0;
        $skipped  = 0;
        $seen     = [];

        foreach ($keys as $key) {
            $key = trim($key);
            if ($key === '' || isset($seen[$key])) {
                $skipped++;
                continue;
            }
            $seen[$key] = true;

            $exists = LicensePoolKey::withoutTenantScope()
                ->where('tenant_id', $tenantId)
                ->where('product_id', $product->id)
                ->where('license_key', $key)
                ->exists();
            if ($exists) {
                $skipped++;
                continue;
            }

            LicensePoolKey::create([
                'tenant_id'   => $tenantId,
                'product_id'  => $product->id,
                'license_key' => $key,
                'status'      => LicensePoolKey::STATUS_AVAILABLE,
                'imported_by' => $userId,
            ]);
            $imported++;
        }

        return ['imported' => $imported, 'skipped' => $skipped];
    }

    /** Alerte d'épuisement (best-effort, canal du tenant, dédupliquée 24 h — recette QA anti-spam). */
    private function notifyPoolExhausted(string $tenantId, Product $product, string $behavior): void
    {
        try {
            // Une seule alerte par produit par 24 h, même si les ventes continuent en mode `generate`.
            if (! \Illuminate\Support\Facades\Cache::add("digital.pool_exhausted:{$tenantId}:{$product->id}", 1, now()->addDay())) {
                return;
            }
            $tenant = Tenant::withoutGlobalScopes()->find($tenantId);
            $recipient = (string) ($tenant->settings['billing_email']
                ?? \App\Models\User::where('tenant_id', $tenantId)->orderBy('created_at')->value('email')
                ?? '');
            if ($recipient === '') {
                return;
            }

            $this->notifications->notify($tenantId, 'digital.pool_exhausted', $recipient, [
                'tenant_name'  => $tenant->name,
                'product_name' => $product->name,
                'behavior'     => $behavior === 'block' ? 'ventes bloquées' : 'génération automatique de clés',
            ]);
        } catch (\Throwable) {
            // best-effort
        }
    }

    /** Clé de licence lisible : 4 groupes de 4 caractères (ex. A1B2-C3D4-E5F6-G7H8). */
    private function generateLicenseKey(): string
    {
        $raw = Str::upper(Str::random(16));

        return implode('-', str_split($raw, 4));
    }
}
