<?php

namespace App\Modules\Inventory\Services;

use App\Modules\Inventory\Models\Stock;
use App\Modules\Inventory\Models\StockMovement;
use Illuminate\Pagination\LengthAwarePaginator;

class InventoryService
{
    public function __construct(private readonly StockService $stock) {}

    /**
     * Process a full delivery: bulk moveIn for multiple products/variants.
     *
     * Each item: ['product_id', 'variant_id'?, 'quantity', 'reference'?, 'note'?]
     */
    public function receiveDelivery(array $items, string $tenantId, string $performedBy): array
    {
        $movements = [];

        foreach ($items as $item) {
            $stockRow = $this->stock->findOrCreate(
                $tenantId,
                $item['product_id'],
                $item['variant_id'] ?? null,
            );

            $movements[] = $this->stock->moveIn(
                $stockRow,
                $item['quantity'],
                StockMovement::REASON_DELIVERY,
                $item['reference'] ?? null,
                $item['note'] ?? null,
                $performedBy,
            );
        }

        return $movements;
    }

    /**
     * Process a physical inventory count: adjust each item to its counted quantity.
     *
     * Each item: ['product_id', 'variant_id'?, 'counted_quantity', 'note'?]
     */
    public function processCount(array $items, string $tenantId, string $performedBy): array
    {
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
    }

    public function movementHistory(Stock $stock, int $perPage = 20): LengthAwarePaginator
    {
        return StockMovement::where('stock_id', $stock->id)
            ->latest()
            ->paginate($perPage);
    }
}
