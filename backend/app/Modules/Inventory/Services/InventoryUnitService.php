<?php

namespace App\Modules\Inventory\Services;

use App\Modules\Inventory\Exceptions\DuplicateSerialException;
use App\Modules\Inventory\Models\InventoryUnit;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Support\SerialNormalizer;
use Illuminate\Support\Facades\DB;

/**
 * RC-5B — réception d'unités sérialisées (IMEI/VIN…). Garantit l'unicité PAR TENANT (valeur normalisée),
 * crée une ligne par unité et incrémente le stock agrégé (+1 par unité) pour rester cohérent avec les
 * vues de stock existantes.
 */
class InventoryUnitService
{
    public function __construct(private readonly StockService $stock) {}

    /**
     * Réceptionne un lot d'unités sérialisées pour un produit. Atomique : si un identifiant est en
     * doublon (dans la requête ou déjà en base), TOUT est annulé.
     *
     * @param array<int,array{serial_type:string,serial_value:string,variant_id?:?string,warehouse_id?:?string,condition?:?string,unit_cost_cents?:?int,notes?:?string}> $items
     * @return array<int,InventoryUnit>
     * @throws DuplicateSerialException
     */
    public function registerMany(string $tenantId, string $productId, array $items, ?string $performedBy = null): array
    {
        return DB::transaction(function () use ($tenantId, $productId, $items, $performedBy) {
            $created   = [];
            $seenInBatch = [];

            foreach ($items as $item) {
                $type       = (string) $item['serial_type'];
                $rawValue   = (string) $item['serial_value'];
                $normalized = SerialNormalizer::normalize($type, $rawValue);

                if ($normalized === '') {
                    throw new DuplicateSerialException($type, $rawValue); // valeur vide après normalisation = invalide
                }

                // Doublon DANS la requête courante.
                $batchKey = strtolower($type) . '|' . $normalized;
                if (isset($seenInBatch[$batchKey])) {
                    throw new DuplicateSerialException($type, $rawValue);
                }
                $seenInBatch[$batchKey] = true;

                // Doublon DÉJÀ en base pour ce tenant (verrou lecture pour éviter la course).
                $exists = InventoryUnit::withoutTenantScope()
                    ->where('tenant_id', $tenantId)
                    ->where('serial_type', strtolower($type))
                    ->where('normalized_serial', $normalized)
                    ->lockForUpdate()
                    ->exists();
                if ($exists) {
                    throw new DuplicateSerialException($type, $rawValue);
                }

                $variantId   = $item['variant_id'] ?? null;
                $warehouseId = $item['warehouse_id'] ?? null;

                $unit = InventoryUnit::create([
                    'tenant_id'         => $tenantId,
                    'product_id'        => $productId,
                    'variant_id'        => $variantId,
                    'warehouse_id'      => $warehouseId,
                    'serial_type'       => strtolower($type),
                    'serial_value'      => $rawValue,
                    'normalized_serial' => $normalized,
                    'condition'         => $item['condition'] ?? InventoryUnit::CONDITION_NEW,
                    'status'            => InventoryUnit::STATUS_IN_STOCK,
                    'received_at'       => now(),
                    'received_by'       => $performedBy,
                    'notes'             => $item['notes'] ?? null,
                ]);

                // Cohérence avec le stock agrégé : chaque unité = +1 dans la cellule produit/variante/entrepôt.
                $stockRow = $this->stock->findOrCreate($tenantId, $productId, $variantId, $warehouseId);
                $this->stock->moveIn(
                    $stockRow,
                    1,
                    StockMovement::REASON_DELIVERY,
                    'serialized-unit',
                    "Unité {$type} {$rawValue}",
                    $performedBy,
                    (int) ($item['unit_cost_cents'] ?? 0),
                );

                $created[] = $unit;
            }

            return $created;
        });
    }

    /** Recherche une unité par identifiant (normalisé), scopée au tenant. */
    public function findBySerial(string $tenantId, string $type, string $value): ?InventoryUnit
    {
        return InventoryUnit::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->where('serial_type', strtolower($type))
            ->where('normalized_serial', SerialNormalizer::normalize($type, $value))
            ->first();
    }
}
