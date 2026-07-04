<?php

namespace App\Modules\Catalog\Services;

use App\Modules\Catalog\Models\KitComponent;
use App\Modules\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * RC-6I — nomenclature des kits. Un kit AVEC nomenclature est virtuel : les opérations de stock de la
 * vente portent sur ses COMPOSANTS (réservation au confirm, sortie au fulfill, libération au cancel —
 * branchées dans OrderService).
 */
class KitService
{
    /**
     * Remplace la nomenclature d'un kit (idempotent).
     *
     * @param array<int,array{product_id:string,variant_id?:?string,quantity:int}> $components
     * @return Collection<int,KitComponent>
     */
    public function setComponents(string $tenantId, Product $kit, array $components): Collection
    {
        return DB::transaction(function () use ($tenantId, $kit, $components) {
            KitComponent::withoutTenantScope()
                ->where('tenant_id', $tenantId)
                ->where('kit_product_id', $kit->id)
                ->delete();

            foreach ($components as $c) {
                KitComponent::create([
                    'tenant_id'            => $tenantId,
                    'kit_product_id'       => $kit->id,
                    'component_product_id' => $c['product_id'],
                    'component_variant_id' => $c['variant_id'] ?? null,
                    'quantity'             => max(1, (int) $c['quantity']),
                ]);
            }

            return $this->componentsFor($tenantId, $kit->id);
        });
    }

    /** Nomenclature d'un kit. */
    public function componentsFor(string $tenantId, string $kitProductId): Collection
    {
        return KitComponent::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->where('kit_product_id', $kitProductId)
            ->with('component:id,name,sku')
            ->get();
    }

    /** Le produit est-il un kit AVEC nomenclature (donc virtuel côté stock) ? */
    public function hasComponents(string $tenantId, string $productId): bool
    {
        return KitComponent::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->where('kit_product_id', $productId)
            ->exists();
    }
}
