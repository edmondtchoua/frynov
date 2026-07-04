<?php

namespace App\Modules\Digital\Services;

use App\Modules\Catalog\Models\Product;
use App\Modules\Digital\Models\DigitalEntitlement;
use App\Modules\Notifications\Services\NotificationService;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderLine;
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

            // Idempotence : ne pas réémettre un accès pour une ligne déjà dotée.
            $already = DigitalEntitlement::withoutTenantScope()
                ->where('tenant_id', $order->tenant_id)
                ->where('order_line_id', $line->id)
                ->exists();
            if ($already) {
                continue;
            }

            $entitlement = $this->grant($order, $line, $product, $userId);
            $this->notifyDelivery($order, $product, $entitlement); // RC-6A — best-effort
            $created[] = $entitlement;
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

        return $entitlement;
    }

    /**
     * RC-5H — révoque les accès digitaux d'une ligne retournée (le client perd le téléchargement/la licence).
     *
     * @return int nombre d'accès révoqués
     */
    public function revokeForOrderLine(string $tenantId, string $orderLineId): int
    {
        return DigitalEntitlement::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->where('order_line_id', $orderLineId)
            ->where('status', DigitalEntitlement::STATUS_ACTIVE)
            ->update([
                'status'     => DigitalEntitlement::STATUS_REVOKED,
                'revoked_at' => now(),
            ]);
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
            ->get();
    }

    private function grant(Order $order, OrderLine $line, Product $product, ?string $userId): DigitalEntitlement
    {
        $isLicense = $product->fulfillment_type === Product::FULFILLMENT_LICENSE;

        return DigitalEntitlement::create([
            'tenant_id'        => $order->tenant_id,
            'product_id'       => $product->id,
            'variant_id'       => $line->variant_id,
            'order_id'         => $order->id,
            'order_line_id'    => $line->id,
            'customer_id'      => $order->customer_id,
            'fulfillment_type' => $product->fulfillment_type,
            'access_token'     => (string) Str::uuid(),
            'license_key'      => $isLicense ? $this->generateLicenseKey() : null,
            'status'           => DigitalEntitlement::STATUS_ACTIVE,
            'granted_at'       => $order->fulfilled_at ?? now(),
            'created_by'       => $userId,
        ]);
    }

    /** Clé de licence lisible : 4 groupes de 4 caractères (ex. A1B2-C3D4-E5F6-G7H8). */
    private function generateLicenseKey(): string
    {
        $raw = Str::upper(Str::random(16));

        return implode('-', str_split($raw, 4));
    }
}
