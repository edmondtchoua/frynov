<?php

namespace App\Modules\Inventory\Services;

use App\Modules\Inventory\Models\Stock;
use App\Modules\Inventory\Models\StockMovement;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    public function __construct(private readonly StockService $stock) {}

    /**
     * Process a full delivery: bulk moveIn for multiple products/variants.
     * Wrapped in a single transaction — if any item fails, all rollback.
     *
     * Each item: ['product_id', 'variant_id'?, 'warehouse_id'?, 'quantity', 'unit_cost_cents'?, 'reference'?, 'note'?]
     */
    public function receiveDelivery(array $items, string $tenantId, string $performedBy): array
    {
        return DB::transaction(function () use ($items, $tenantId, $performedBy) {
            $movements = [];

            foreach ($items as $item) {
                $stockRow = $this->stock->findOrCreate(
                    $tenantId,
                    $item['product_id'],
                    $item['variant_id'] ?? null,
                    $item['warehouse_id'] ?? null,
                );

                $movements[] = $this->stock->moveIn(
                    $stockRow,
                    $item['quantity'],
                    StockMovement::REASON_DELIVERY,
                    $item['reference'] ?? null,
                    $item['note'] ?? null,
                    $performedBy,
                    $item['unit_cost_cents'] ?? 0,
                );
            }

            return $movements;
        });
    }

    /**
     * Process a physical inventory count: adjust each item to its counted quantity.
     * Wrapped in a single transaction — all adjustments or none.
     *
     * Each item: ['product_id', 'variant_id'?, 'counted_quantity', 'note'?]
     */
    public function processCount(array $items, string $tenantId, string $performedBy): array
    {
        return DB::transaction(function () use ($items, $tenantId, $performedBy) {
            $movements = [];

            foreach ($items as $item) {
                $stockRow = $this->stock->findOrCreate(
                    $tenantId,
                    $item['product_id'],
                    $item['variant_id'] ?? null,
                );

                $movements[] = $this->stock->adjust(
                    $stockRow,
                    $item['counted_quantity'],
                    StockMovement::REASON_COUNT,
                    $item['note'] ?? null,
                    $performedBy,
                );
            }

            return $movements;
        });
    }

    public function movementHistory(Stock $stock, int $perPage = 20): LengthAwarePaginator
    {
        return StockMovement::where('stock_id', $stock->id)
            ->latest()
            ->paginate($perPage);
    }
}
