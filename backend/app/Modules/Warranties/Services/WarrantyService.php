<?php

namespace App\Modules\Warranties\Services;

use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\InventoryUnit;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderLine;
use App\Modules\Warranties\Models\WarrantyContract;
use App\Modules\Warranties\Models\WarrantyPolicy;
use Illuminate\Database\Eloquent\Collection;

/**
 * RC-5D — génération des contrats de garantie à la vente.
 *
 * À la livraison (`OrderService::fulfill`), pour chaque ligne dont le produit porte une politique de
 * garantie active, on émet un contrat :
 *   - produit **sérialisé** → un contrat PAR unité vendue (rattaché à l'IMEI/VIN) + horodatage de la
 *     période sur l'unité (`warranty_started_at`/`warranty_ends_at`) ;
 *   - sinon → un contrat pour la ligne.
 *
 * La période est figée à l'émission : `starts_at` (date de vente) + `duration_months` → `ends_at`.
 * Idempotent : une ligne déjà couverte n'est pas réémise.
 */
class WarrantyService
{
    /**
     * @return array<int,WarrantyContract> contrats créés
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
            if (! $product || ! $product->warranty_policy_id) {
                continue;
            }

            $policy = WarrantyPolicy::withoutTenantScope()
                ->where('tenant_id', $order->tenant_id)
                ->where('id', $product->warranty_policy_id)
                ->where('is_active', true)
                ->first();
            if (! $policy) {
                continue;
            }

            // Idempotence : ne jamais réémettre pour une ligne déjà couverte.
            $already = WarrantyContract::withoutTenantScope()
                ->where('tenant_id', $order->tenant_id)
                ->where('order_line_id', $line->id)
                ->exists();
            if ($already) {
                continue;
            }

            $startsAt = $order->fulfilled_at ?? now();
            $endsAt   = $startsAt->copy()->addMonths($policy->duration_months);

            if ($product->stock_tracking === Product::STOCK_TRACKING_SERIALIZED) {
                $units = InventoryUnit::withoutTenantScope()
                    ->where('tenant_id', $order->tenant_id)
                    ->where('order_line_id', $line->id)
                    ->get();

                foreach ($units as $unit) {
                    $created[] = $this->createContract($order, $line, $product, $policy, $startsAt, $endsAt, $userId, $unit);
                    $unit->update([
                        'warranty_started_at' => $startsAt,
                        'warranty_ends_at'    => $endsAt,
                    ]);
                }
            } else {
                $created[] = $this->createContract($order, $line, $product, $policy, $startsAt, $endsAt, $userId, null);
            }
        }

        return $created;
    }

    /**
     * RC-5H — annule (`void`) les contrats de garantie d'une ligne retournée. Cible les contrats des
     * unités sérialisées retournées (`$unitIds`) ou, à défaut (produit agrégé), ceux de la ligne.
     *
     * @param array<int,string> $unitIds unités sérialisées concernées (vide → cible la ligne)
     * @return int nombre de contrats annulés
     */
    public function voidForReturn(string $tenantId, string $orderLineId, array $unitIds = []): int
    {
        $query = WarrantyContract::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->where('status', WarrantyContract::STATUS_ACTIVE);

        if (! empty($unitIds)) {
            $query->whereIn('inventory_unit_id', $unitIds);
        } else {
            $query->where('order_line_id', $orderLineId);
        }

        return $query->update(['status' => WarrantyContract::STATUS_VOID]);
    }

    /** Contrats de garantie rattachés à une commande (traçabilité). */
    public function forOrder(string $tenantId, string $orderId): Collection
    {
        return WarrantyContract::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->where('order_id', $orderId)
            ->orderBy('order_line_id')
            ->get();
    }

    private function createContract(
        Order $order,
        OrderLine $line,
        Product $product,
        WarrantyPolicy $policy,
        \Illuminate\Support\Carbon $startsAt,
        \Illuminate\Support\Carbon $endsAt,
        ?string $userId,
        ?InventoryUnit $unit,
    ): WarrantyContract {
        return WarrantyContract::create([
            'tenant_id'          => $order->tenant_id,
            'warranty_policy_id' => $policy->id,
            'product_id'         => $product->id,
            'variant_id'         => $line->variant_id,
            'inventory_unit_id'  => $unit?->id,
            'order_id'           => $order->id,
            'order_line_id'      => $line->id,
            'customer_id'        => $order->customer_id,
            'serial_value'       => $unit?->serial_value,
            'starts_at'          => $startsAt,
            'ends_at'            => $endsAt,
            'status'             => WarrantyContract::STATUS_ACTIVE,
            'created_by'         => $userId,
        ]);
    }
}
