<?php

namespace App\Modules\Inventory\Services;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Inventory\Exceptions\InsufficientStockException;
use App\Modules\Inventory\Exceptions\ProductNotFoundException;
use App\Modules\Inventory\Exceptions\StockLockException;
use App\Modules\Inventory\Models\Stock;
use App\Modules\Inventory\Models\StockMovement;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class StockService
{
    private const LOCK_TTL = 5; // seconds

    // ── Resolution ─────────────────────────────────────────────────────────

    public function findOrCreate(string $tenantId, string $productId, ?string $variantId = null): Stock
    {
        return Stock::firstOrCreate(
            ['tenant_id' => $tenantId, 'product_id' => $productId, 'variant_id' => $variantId],
            ['quantity' => 0, 'reserved_quantity' => 0, 'low_stock_threshold' => 5],
        );
    }

    /**
     * Resolve a Stock row by scanning a SKU — looks up products then variants.
     * Used by the scan-to-action endpoint on the POS.
     */
    public function findBySku(string $sku, string $tenantId): Stock
    {
        $product = Product::where('tenant_id', $tenantId)
            ->where('sku', $sku)
            ->whereIn('status', ['active', 'draft'])
            ->first();

        if ($product && ! $product->has_variants) {
            return $this->findOrCreate($tenantId, $product->id, null);
        }

        $variant = ProductVariant::where('tenant_id', $tenantId)
            ->where('sku', $sku)
            ->first();

        if ($variant) {
            return $this->findOrCreate($tenantId, $variant->product_id, $variant->id);
        }

        throw new ProductNotFoundException($sku);
    }

    // ── Quantity helpers ───────────────────────────────────────────────────

    public function available(Stock $stock): int
    {
        return $stock->available();
    }

    // ── Stock movements ────────────────────────────────────────────────────

    public function moveIn(
        Stock $stock,
        int $quantity,
        string $reason = StockMovement::REASON_DELIVERY,
        ?string $reference = null,
        ?string $note = null,
        ?string $performedBy = null,
    ): StockMovement {
        return DB::transaction(function () use ($stock, $quantity, $reason, $reference, $note, $performedBy) {
            $before = $stock->quantity;
            $stock->increment('quantity', $quantity);

            return $this->record($stock, StockMovement::TYPE_IN, $quantity, $before, $reason, $reference, $note, $performedBy);
        });
    }

    /**
     * Decrement stock with Redis lock to prevent oversell.
     *
     * @throws InsufficientStockException
     * @throws StockLockException
     */
    public function moveOut(
        Stock $stock,
        int $quantity,
        string $reason = StockMovement::REASON_SALE,
        ?string $reference = null,
        ?string $note = null,
        ?string $performedBy = null,
    ): StockMovement {
        $lock = Cache::lock("inventory.stock.{$stock->id}", self::LOCK_TTL);

        if (! $lock->get()) {
            throw new StockLockException($stock->id);
        }

        try {
            return DB::transaction(function () use ($stock, $quantity, $reason, $reference, $note, $performedBy) {
                $stock->refresh();

                if ($stock->available() < $quantity) {
                    throw new InsufficientStockException(
                        $stock->variant?->sku ?? $stock->product->sku,
                        $stock->available(),
                        $quantity,
                    );
                }

                $before = $stock->quantity;
                $stock->decrement('quantity', $quantity);

                return $this->record($stock, StockMovement::TYPE_OUT, $quantity, $before, $reason, $reference, $note, $performedBy);
            });
        } finally {
            $lock->release();
        }
    }

    /**
     * Set an absolute quantity (used during physical inventory counts).
     *
     * @throws StockLockException
     */
    public function adjust(
        Stock $stock,
        int $newQuantity,
        string $reason = StockMovement::REASON_COUNT,
        ?string $note = null,
        ?string $performedBy = null,
    ): StockMovement {
        $lock = Cache::lock("inventory.stock.{$stock->id}", self::LOCK_TTL);

        if (! $lock->get()) {
            throw new StockLockException($stock->id);
        }

        try {
            return DB::transaction(function () use ($stock, $newQuantity, $reason, $note, $performedBy) {
                $stock->refresh();
                $before = $stock->quantity;
                $diff   = $newQuantity - $before;

                $stock->update(['quantity' => $newQuantity]);

                return $this->record(
                    $stock,
                    StockMovement::TYPE_ADJUSTMENT,
                    abs($diff),
                    $before,
                    $reason,
                    null,
                    $note,
                    $performedBy,
                );
            });
        } finally {
            $lock->release();
        }
    }

    /**
     * Reserve quantity for a pending order.
     *
     * @throws InsufficientStockException
     * @throws StockLockException
     */
    public function reserve(Stock $stock, int $quantity): void
    {
        $lock = Cache::lock("inventory.stock.{$stock->id}", self::LOCK_TTL);

        if (! $lock->get()) {
            throw new StockLockException($stock->id);
        }

        try {
            $stock->refresh();

            if ($stock->available() < $quantity) {
                throw new InsufficientStockException(
                    $stock->variant?->sku ?? $stock->product->sku,
                    $stock->available(),
                    $quantity,
                );
            }

            $stock->increment('reserved_quantity', $quantity);
        } finally {
            $lock->release();
        }
    }

    public function release(Stock $stock, int $quantity): void
    {
        $stock->decrement('reserved_quantity', min($quantity, $stock->reserved_quantity));
    }

    // ── Alerts ─────────────────────────────────────────────────────────────

    public function lowStockItems(string $tenantId): Collection
    {
        return Stock::where('tenant_id', $tenantId)
            ->whereColumn('quantity', '<=', 'low_stock_threshold')
            ->with(['product', 'variant'])
            ->get();
    }

    // ── Private ────────────────────────────────────────────────────────────

    private function record(
        Stock $stock,
        string $type,
        int $quantity,
        int $before,
        string $reason,
        ?string $reference,
        ?string $note,
        ?string $performedBy,
    ): StockMovement {
        $stock->refresh();

        return StockMovement::create([
            'tenant_id'       => $stock->tenant_id,
            'stock_id'        => $stock->id,
            'product_id'      => $stock->product_id,
            'variant_id'      => $stock->variant_id,
            'type'            => $type,
            'quantity'        => $quantity,
            'quantity_before' => $before,
            'quantity_after'  => $stock->quantity,
            'reason'          => $reason,
            'reference'       => $reference,
            'note'            => $note,
            'performed_by'    => $performedBy,
        ]);
    }
}
