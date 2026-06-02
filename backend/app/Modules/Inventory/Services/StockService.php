<?php

namespace App\Modules\Inventory\Services;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Inventory\Events\LowStockDetected;
use App\Modules\Inventory\Events\StockUpdated;
use App\Modules\Inventory\Exceptions\InsufficientStockException;
use App\Modules\Inventory\Exceptions\ProductNotFoundException;
use App\Modules\Inventory\Exceptions\StockLockException;
use App\Modules\Inventory\Jobs\RecalculateCmupJob;
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
            ['quantity' => 0, 'reserved_quantity' => 0, 'low_stock_threshold' => 5, 'unit_cost_cents' => 0, 'total_value_cents' => 0],
        );
    }

    public function findBySku(string $sku, string $tenantId): Stock
    {
        $product = Product::where('tenant_id', $tenantId)
            ->where('sku', $sku)
            ->whereIn('status', ['active', 'draft'])
            ->first();

        if ($product && ! $product->has_variants) {
            return $this->findOrCreate($tenantId, $product->id, null);
        }

        $variant = ProductVariant::where('tenant_id', $tenantId)->where('sku', $sku)->first();

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

    /**
     * Increment stock with CMUP recalculation.
     *
     * @param int $unitCostCents  Unit cost of this specific receipt (0 = keep current CMUP)
     */
    public function moveIn(
        Stock   $stock,
        int     $quantity,
        string  $reason = StockMovement::REASON_DELIVERY,
        ?string $reference = null,
        ?string $note = null,
        ?string $performedBy = null,
        int     $unitCostCents = 0,
    ): StockMovement {
        // Axe 4 — Period lock guard (double protection: applicative + MySQL trigger)
        PeriodLockService::assertOperationAllowed($stock->tenant_id);

        return DB::transaction(function () use ($stock, $quantity, $reason, $reference, $note, $performedBy, $unitCostCents) {
            // Lock the row to prevent concurrent CMUP drift
            $locked = Stock::where('id', $stock->id)->lockForUpdate()->firstOrFail();
            $before = $locked->quantity;

            // ── CMUP perpétuel ─────────────────────────────────────────────
            // CMUP(n+1) = [ValeurActuelle + (Qté × PrixEntrée)] / [QttéActuelle + Qté]
            $newQty       = $locked->quantity + $quantity;
            $entryValue   = $quantity * $unitCostCents;
            $currentValue = $locked->total_value_cents;

            $newCmup = $newQty > 0
                ? (int) round(($currentValue + $entryValue) / $newQty)
                : ($unitCostCents ?: $locked->unit_cost_cents);

            $locked->update([
                'quantity'          => $newQty,
                'unit_cost_cents'   => $newCmup,
                'total_value_cents' => $newQty * $newCmup,
            ]);

            return $this->record($locked, StockMovement::TYPE_IN, $quantity, $before, $reason, $reference, $note, $performedBy);
        });
    }

    /**
     * Axe 1 — Bulk import with deferred CMUP recalculation.
     * Use during CSV imports or API sync to avoid N individual CMUP recalculations.
     * The actual CMUP is recomputed asynchronously by RecalculateCmupJob.
     *
     * @param array $items  [['stock' => Stock, 'quantity' => int, 'unit_cost_cents' => int, 'reference' => ?string]]
     */
    public function moveInBulk(array $items, string $performedBy): array
    {
        $movements = [];

        foreach ($items as $item) {
            /** @var Stock $stock */
            $stock         = $item['stock'];
            $quantity      = (int) $item['quantity'];
            // Sprint 11: unit_cost_cents must come from internal/trusted callers only,
            // never from raw HTTP payloads. Strip it or use 0 if not from a trusted source.
            // The $item['_trusted_cost'] key is set only by internal services (ImportExport, etc.).
            $unitCost = isset($item['_trusted_cost']) ? (int) $item['_trusted_cost'] : 0;
            $reference     = $item['reference'] ?? null;

            // Axe 4 — Period lock guard
            PeriodLockService::assertOperationAllowed($stock->tenant_id);

            $movements[] = DB::transaction(function () use ($stock, $quantity, $unitCost, $reference, $performedBy) {
                $locked = Stock::where('id', $stock->id)->lockForUpdate()->firstOrFail();
                $before = $locked->quantity;

                // Update quantity immediately, CMUP will be recalculated async
                $locked->update([
                    'quantity'          => $locked->quantity + $quantity,
                    // Keep current CMUP temporarily (will be corrected by job)
                    'total_value_cents' => ($locked->quantity + $quantity) * $locked->unit_cost_cents,
                ]);

                return StockMovement::create([
                    'tenant_id'                => $locked->tenant_id,
                    'stock_id'                 => $locked->id,
                    'product_id'               => $locked->product_id,
                    'variant_id'               => $locked->variant_id,
                    'type'                     => StockMovement::TYPE_IN,
                    'quantity'                 => $quantity,
                    'quantity_before'          => $before,
                    'quantity_after'           => $locked->quantity + $quantity,
                    'reason'                   => StockMovement::REASON_DELIVERY,
                    'reference'                => $reference,
                    'note'                     => 'Import groupé (CMUP différé)',
                    'performed_by'             => $performedBy,
                    'unit_cost_cents_snapshot' => $unitCost,
                    'cmup_deferred'            => true,
                ]);
            });

            // Dispatch async CMUP recalculation
            RecalculateCmupJob::dispatch($stock->id, $stock->tenant_id)->onQueue('cmup-recalc');
        }

        return $movements;
    }

    /**
     * Decrement stock with Redis lock to prevent oversell.
     * Fires LowStockDetected event if stock drops to/below threshold.
     *
     * @throws InsufficientStockException
     * @throws StockLockException
     */
    public function moveOut(
        Stock   $stock,
        int     $quantity,
        string  $reason = StockMovement::REASON_SALE,
        ?string $reference = null,
        ?string $note = null,
        ?string $performedBy = null,
    ): StockMovement {
        // Axe 4 — Period lock guard
        PeriodLockService::assertOperationAllowed($stock->tenant_id);

        $lock = Cache::lock("inventory.stock.{$stock->id}", self::LOCK_TTL);

        if (! $lock->get()) {
            throw new StockLockException($stock->id);
        }

        try {
            $movement = DB::transaction(function () use ($stock, $quantity, $reason, $reference, $note, $performedBy) {
                $locked = Stock::where('id', $stock->id)->lockForUpdate()->firstOrFail();

                if ($locked->available() < $quantity) {
                    throw new InsufficientStockException(
                        $locked->variant?->sku ?? $locked->product->sku,
                        $locked->available(),
                        $quantity,
                    );
                }

                $before = $locked->quantity;
                $newQty = $locked->quantity - $quantity;

                $locked->update([
                    'quantity'          => $newQty,
                    'total_value_cents' => $newQty * $locked->unit_cost_cents,
                ]);

                return $this->record($locked, StockMovement::TYPE_OUT, $quantity, $before, $reason, $reference, $note, $performedBy);
            });

            // Post-transaction: fire event if stock is now low (outside transaction = no rollback risk)
            $stock->refresh();
            if ($stock->low_stock_threshold > 0 && $stock->available() <= $stock->low_stock_threshold) {
                event(new LowStockDetected($stock));
            }

            return $movement;
        } finally {
            event(new StockUpdated($stock->refresh(), -$quantity, 'api'));
            $lock->release();
        }
    }

    /**
     * Set an absolute quantity (used during physical inventory counts).
     *
     * @throws StockLockException
     */
    public function adjust(
        Stock   $stock,
        int     $newQuantity,
        string  $reason = StockMovement::REASON_COUNT,
        ?string $note = null,
        ?string $performedBy = null,
        ?string $reference = null,
    ): StockMovement {
        // Axe 4 — Period lock guard
        PeriodLockService::assertOperationAllowed($stock->tenant_id);

        $lock = Cache::lock("inventory.stock.{$stock->id}", self::LOCK_TTL);

        if (! $lock->get()) {
            throw new StockLockException($stock->id);
        }

        try {
            return DB::transaction(function () use ($stock, $newQuantity, $reason, $note, $performedBy, $reference) {
                $locked = Stock::where('id', $stock->id)->lockForUpdate()->firstOrFail();
                $before = $locked->quantity;
                $diff   = $newQuantity - $before;

                $locked->update([
                    'quantity'          => $newQuantity,
                    'total_value_cents' => $newQuantity * $locked->unit_cost_cents,
                ]);

                return $this->record(
                    $locked,
                    StockMovement::TYPE_ADJUSTMENT,
                    abs($diff),
                    $before,
                    $reason,
                    $reference,
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
     * Now uses DB::transaction + lockForUpdate in addition to the Redis lock.
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
            DB::transaction(function () use ($stock, $quantity) {
                $locked = Stock::where('id', $stock->id)->lockForUpdate()->firstOrFail();

                if ($locked->available() < $quantity) {
                    throw new InsufficientStockException(
                        $locked->variant?->sku ?? $locked->product->sku,
                        $locked->available(),
                        $quantity,
                    );
                }

                $locked->increment('reserved_quantity', $quantity);
                // Sync caller's reference
                $stock->setRawAttributes($locked->fresh()->getAttributes(), true);
            });
        } finally {
            $lock->release();
        }
    }

    public function release(Stock $stock, int $quantity): void
    {
        // Sprint 11: use lock to prevent race condition under concurrent order cancellations
        $lock = Cache::lock("inventory.stock.{$stock->id}", self::LOCK_TTL);
        if ($lock->get()) {
            try {
                DB::transaction(function () use ($stock, $quantity) {
                    $locked = Stock::where('id', $stock->id)->lockForUpdate()->firstOrFail();
                    $locked->decrement('reserved_quantity', min($quantity, $locked->reserved_quantity));
                });
            } finally {
                $lock->release();
            }
        }
        // If lock not acquired, the decrement is skipped (safe: reserved_quantity drift is non-critical vs oversell)
    }

    // ── Alerts — uses AVAILABLE (qty - reserved), not raw quantity ─────────

    public function lowStockItems(string $tenantId): Collection
    {
        return Stock::where('tenant_id', $tenantId)
            ->where('low_stock_threshold', '>', 0)
            ->whereRaw('(quantity - reserved_quantity) <= low_stock_threshold')
            ->with(['product:id,name,sku,price_amount,cost_amount', 'variant:id,sku,label'])
            ->get();
    }

    /**
     * Total available stock across ALL warehouses for a given SKU.
     * Used by marketplace sync to determine whether to close a listing.
     */
    public function totalAvailableForTenant(
        string $tenantId,
        string $productId,
        ?string $variantId = null,
    ): int {
        return (int) Stock::where('tenant_id', $tenantId)
            ->where('product_id', $productId)
            ->where('variant_id', $variantId)
            ->selectRaw('COALESCE(SUM(quantity - reserved_quantity), 0) AS total')
            ->value('total');
    }

    // ── Private ────────────────────────────────────────────────────────────

    private function record(
        Stock   $stock,
        string  $type,
        int     $quantity,
        int     $before,
        string  $reason,
        ?string $reference,
        ?string $note,
        ?string $performedBy,
    ): StockMovement {
        $stock->refresh();

        return StockMovement::create([
            'tenant_id'                => $stock->tenant_id,
            'stock_id'                 => $stock->id,
            'product_id'               => $stock->product_id,
            'variant_id'               => $stock->variant_id,
            'type'                     => $type,
            'quantity'                 => $quantity,
            'quantity_before'          => $before,
            'quantity_after'           => $stock->quantity,
            'reason'                   => $reason,
            'reference'                => $reference,
            'note'                     => $note,
            'performed_by'             => $performedBy,
            // Axe 1 — snapshot current CMUP at time of movement for replay
            'unit_cost_cents_snapshot' => $stock->unit_cost_cents,
            'cmup_deferred'            => false,
        ]);
    }
}
