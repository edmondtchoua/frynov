<?php

namespace App\Modules\Inventory\Services;

use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Exceptions\InsufficientUnitsException;
use App\Modules\Inventory\Models\InventoryUnit;
use Illuminate\Database\Eloquent\Collection;

/**
 * RC-5C — lien commande ⇄ unité sérialisée ⇄ client.
 *
 * Pour les produits `stock_tracking=serialized`, en plus du stock agrégé (miroir RC-5B), on réserve des
 * UNITÉS PRÉCISES (IMEI/VIN) sur la ligne de commande :
 *   - `confirm` → {@see allocate()}  : in_stock → reserved, rattachées à order_id/order_line_id.
 *   - `fulfill` → {@see markSold()}  : reserved → sold (+ sold_at + customer_id).
 *   - `cancel`  → {@see release()}   : reserved → in_stock, rattachements effacés.
 *
 * L'allocation pose un verrou lecture (`lockForUpdate`) sur les unités choisies, donc deux commandes
 * concurrentes ne peuvent pas réserver la même unité (anti double-vente). À appeler DANS la transaction
 * de l'opération de commande pour rester atomique avec le stock agrégé.
 */
class SerializedAllocationService
{
    /**
     * Réserve `quantity` unités DISPONIBLES (status in_stock) pour une ligne, FIFO (received_at).
     * Échoue (rien réservé : la transaction appelante annule) si l'unitaire est insuffisant.
     *
     * @return array<int,InventoryUnit> unités réservées
     * @throws InsufficientUnitsException
     */
    public function allocate(
        string $tenantId,
        string $orderId,
        string $orderLineId,
        string $productId,
        ?string $variantId,
        int $quantity,
        ?string $warehouseId = null,
    ): array {
        $units = InventoryUnit::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->where('product_id', $productId)
            ->where('status', InventoryUnit::STATUS_IN_STOCK)
            // variant_id exact (NULL inclus) : ne jamais piocher l'unité d'une autre déclinaison.
            ->when(
                $variantId !== null,
                fn ($q) => $q->where('variant_id', $variantId),
                fn ($q) => $q->whereNull('variant_id'),
            )
            // Entrepôt de la commande quand il est connu, sinon n'importe quel entrepôt du tenant.
            ->when($warehouseId !== null, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->orderBy('received_at')   // FIFO : on vend d'abord les unités reçues en premier
            ->orderBy('created_at')
            ->limit($quantity)
            ->lockForUpdate()
            ->get();

        if ($units->count() < $quantity) {
            $sku = Product::withoutTenantScope()
                ->where('tenant_id', $tenantId)
                ->where('id', $productId)
                ->value('sku') ?? $productId;

            throw new InsufficientUnitsException($sku, $units->count(), $quantity);
        }

        foreach ($units as $unit) {
            $unit->update([
                'status'        => InventoryUnit::STATUS_RESERVED,
                'order_id'      => $orderId,
                'order_line_id' => $orderLineId,
            ]);
        }

        return $units->all();
    }

    /**
     * Marque vendues les unités réservées par une ligne (au fulfillment) et les rattache au client.
     *
     * @return int nombre d'unités passées à `sold`
     */
    public function markSold(string $tenantId, string $orderLineId, ?string $customerId): int
    {
        return InventoryUnit::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->where('order_line_id', $orderLineId)
            ->where('status', InventoryUnit::STATUS_RESERVED)
            ->update([
                'status'      => InventoryUnit::STATUS_SOLD,
                'sold_at'     => now(),
                'customer_id' => $customerId,
            ]);
    }

    /**
     * Relâche les unités réservées par une ligne (annulation d'une commande confirmée) : elles
     * redeviennent disponibles et perdent tout rattachement commande/client.
     *
     * @return int nombre d'unités remises en stock
     */
    public function release(string $tenantId, string $orderLineId): int
    {
        return InventoryUnit::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->where('order_line_id', $orderLineId)
            ->where('status', InventoryUnit::STATUS_RESERVED)
            ->update([
                'status'        => InventoryUnit::STATUS_IN_STOCK,
                'order_id'      => null,
                'order_line_id' => null,
                'customer_id'   => null,
            ]);
    }

    /** Unités (réservées ou vendues) rattachées à une commande — traçabilité vente/SAV. */
    public function forOrder(string $tenantId, string $orderId): Collection
    {
        return InventoryUnit::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->where('order_id', $orderId)
            ->orderBy('order_line_id')
            ->orderBy('received_at')
            ->get();
    }
}
