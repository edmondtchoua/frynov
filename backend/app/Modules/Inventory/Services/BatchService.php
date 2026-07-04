<?php

namespace App\Modules\Inventory\Services;

use App\Modules\Inventory\Models\ProductBatch;
use App\Modules\Inventory\Models\StockMovement;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * RC-6H (Phase 2H — lots/péremption) — réception PAR LOT et consommation **FEFO** (First Expired,
 * First Out) à la vente pour les produits `stock_tracking=batch`. Le stock agrégé reste le miroir
 * (mêmes vues qu'ailleurs) ; les lots portent la vérité fine (péremption, rappel).
 */
class BatchService
{
    public function __construct(private readonly StockService $stock) {}

    /**
     * Réceptionne un lot : ligne `product_batches` + incrément du stock agrégé miroir.
     *
     * @param array{batch_number:string,expiry_date?:?string,manufacturing_date?:?string,variant_id?:?string,warehouse_id?:?string,quantity:int,unit_cost_cents?:?int,notes?:?string} $data
     */
    public function receive(string $tenantId, string $productId, array $data, ?string $performedBy = null): ProductBatch
    {
        return DB::transaction(function () use ($tenantId, $productId, $data, $performedBy) {
            $batch = ProductBatch::create([
                'tenant_id'          => $tenantId,
                'product_id'         => $productId,
                'variant_id'         => $data['variant_id'] ?? null,
                'batch_number'       => $data['batch_number'],
                'expiry_date'        => $data['expiry_date'] ?? null,
                'manufacturing_date' => $data['manufacturing_date'] ?? null,
                'quantity_initial'   => (int) $data['quantity'],
                'quantity'           => (int) $data['quantity'],
                'status'             => ProductBatch::STATUS_ACTIVE,
                'notes'              => $data['notes'] ?? null,
                'received_by'        => $performedBy,
            ]);

            $stockRow = $this->stock->findOrCreate($tenantId, $productId, $data['variant_id'] ?? null, $data['warehouse_id'] ?? null);
            $this->stock->moveIn(
                $stockRow,
                (int) $data['quantity'],
                StockMovement::REASON_DELIVERY,
                'batch:' . $data['batch_number'],
                "Lot {$data['batch_number']}" . (isset($data['expiry_date']) ? " (DLC {$data['expiry_date']})" : ''),
                $performedBy,
                (int) ($data['unit_cost_cents'] ?? 0),
            );

            return $batch;
        });
    }

    /**
     * Consomme `quantity` unités en FEFO (péremption la plus proche d'abord, lots sans date en
     * dernier). Best-effort de traçabilité : si les lots ne couvrent pas tout (drift historique),
     * on consomme ce qui existe sans bloquer la vente (le stock agrégé a déjà validé la quantité).
     *
     * @return array<int,array{batch_id:string,batch_number:string,quantity:int}> répartition consommée
     */
    public function allocateFefo(string $tenantId, string $productId, ?string $variantId, int $quantity): array
    {
        $remaining = $quantity;
        $allocated = [];

        $batches = ProductBatch::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->where('product_id', $productId)
            ->when(
                $variantId !== null,
                fn ($q) => $q->where('variant_id', $variantId),
                fn ($q) => $q->whereNull('variant_id'),
            )
            ->where('status', ProductBatch::STATUS_ACTIVE)
            ->where('quantity', '>', 0)
            ->orderByRaw('expiry_date IS NULL')  // datés d'abord
            ->orderBy('expiry_date')             // FEFO
            ->orderBy('created_at')
            ->lockForUpdate()
            ->get();

        foreach ($batches as $batch) {
            if ($remaining <= 0) {
                break;
            }
            $take = min($batch->quantity, $remaining);
            $newQty = $batch->quantity - $take;
            $batch->update([
                'quantity' => $newQty,
                'status'   => $newQty === 0 ? ProductBatch::STATUS_EXHAUSTED : ProductBatch::STATUS_ACTIVE,
            ]);
            $allocated[] = ['batch_id' => $batch->id, 'batch_number' => $batch->batch_number, 'quantity' => $take];
            $remaining -= $take;
        }

        return $allocated;
    }

    /** Lots d'un produit (scopés tenant). */
    public function forProduct(string $tenantId, string $productId): Collection
    {
        return ProductBatch::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->where('product_id', $productId)
            ->orderByRaw('expiry_date IS NULL')
            ->orderBy('expiry_date')
            ->get();
    }

    /** Lots actifs expirant sous `days` jours (alerte péremption / démarque). */
    public function expiring(string $tenantId, int $days = 30): Collection
    {
        return ProductBatch::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->where('status', ProductBatch::STATUS_ACTIVE)
            ->where('quantity', '>', 0)
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '<=', now()->addDays($days)->toDateString())
            ->orderBy('expiry_date')
            ->get();
    }
}
