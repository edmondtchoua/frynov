<?php
namespace App\Modules\Inventory\Services;

use App\Modules\Inventory\Models\Stock;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\StockTransfer;
use App\Modules\Inventory\Models\StockTransferLine;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Exceptions\InsufficientStockException;
use Illuminate\Support\Facades\DB;

/**
 * Axe 2 — Stock transfer state machine.
 * Prevents "black holes" by ensuring stock is always traceable (in source, in transit, or in destination).
 * Handles partial reception and dispute resolution.
 */
class StockTransferService
{
    public function __construct(private readonly StockService $stock) {}

    // ── 1. CREATE (state: draft) ──────────────────────────────────────────────

    public function create(
        string  $tenantId,
        string  $sourceWarehouseId,
        string  $destWarehouseId,
        array   $lines,         // [['product_id','variant_id','quantity']]
        string  $requestedBy,
        ?string $notes = null,
    ): StockTransfer {
        if ($sourceWarehouseId === $destWarehouseId) {
            throw new \DomainException('Source et destination ne peuvent pas être identiques.');
        }

        // Both warehouses must belong to this tenant
        $found = Warehouse::where('tenant_id', $tenantId)
            ->whereIn('id', [$sourceWarehouseId, $destWarehouseId])
            ->count();
        if ($found !== 2) {
            throw new \DomainException('Les entrepôts doivent appartenir au même tenant.');
        }

        return DB::transaction(function () use ($tenantId, $sourceWarehouseId, $destWarehouseId, $lines, $requestedBy, $notes) {
            $count    = StockTransfer::where('tenant_id', $tenantId)->withTrashed()->count();
            $transfer = StockTransfer::create([
                'tenant_id'                => $tenantId,
                'number'                   => 'TRF-' . str_pad($count + 1, 6, '0', STR_PAD_LEFT),
                'source_warehouse_id'      => $sourceWarehouseId,
                'destination_warehouse_id' => $destWarehouseId,
                'status'                   => 'draft',
                'requested_by'             => $requestedBy,
                'notes'                    => $notes,
            ]);

            foreach ($lines as $l) {
                $transfer->lines()->create([
                    'product_id'         => $l['product_id'],
                    'variant_id'         => $l['variant_id'] ?? null,
                    'quantity_requested' => (int) $l['quantity'],
                    'line_status'        => 'pending',
                ]);
            }

            return $transfer->load('lines');
        });
    }

    // ── 2. SHIP (draft|requested → in_transit) ────────────────────────────────
    // CRITICAL: removes stock from source, creates transit state

    public function ship(StockTransfer $transfer, string $shippedBy, array $quantities = []): void
    {
        $this->assertState($transfer, ['draft','requested'], 'ship');

        DB::transaction(function () use ($transfer, $shippedBy, $quantities) {
            foreach ($transfer->lines as $line) {
                $qty = $quantities[$line->id] ?? $line->quantity_requested;

                $src = Stock::where('tenant_id', $transfer->tenant_id)
                    ->where('warehouse_id', $transfer->source_warehouse_id)
                    ->where('product_id', $line->product_id)
                    ->when($line->variant_id, fn ($q) => $q->where('variant_id', $line->variant_id), fn ($q) => $q->whereNull('variant_id'))
                    ->lockForUpdate()->first();

                if (! $src || $src->available() < $qty) {
                    throw new InsufficientStockException(
                        $line->product->sku ?? 'unknown', $src?->available() ?? 0, $qty
                    );
                }

                // Snapshot CMUP at time of shipment (for FIFO accuracy on receipt)
                $cmupAtTransfer = $src->unit_cost_cents;

                $this->stock->moveOut(
                    $src, $qty, StockMovement::REASON_TRANSFER,
                    $transfer->number,
                    "Transfert vers " . $transfer->destinationWarehouse?->name,
                    $shippedBy,
                );

                $line->update([
                    'quantity_shipped'            => $qty,
                    'unit_cost_cents_at_transfer' => $cmupAtTransfer,
                    'line_status'                 => 'shipped',
                ]);
            }

            $transfer->update([
                'status'     => 'in_transit',
                'shipped_by' => $shippedBy,
                'shipped_at' => now(),
            ]);
        });
    }

    // ── 3. RECEIVE (in_transit → received|partial) ───────────────────────────

    public function receive(StockTransfer $transfer, string $receivedBy, array $quantities): void
    {
        $this->assertState($transfer, ['in_transit'], 'receive');

        DB::transaction(function () use ($transfer, $receivedBy, $quantities) {
            $hasDisc = false;

            foreach ($transfer->lines as $line) {
                $rcv  = (int) ($quantities[$line->id] ?? 0);
                $disc = $line->quantity_shipped - $rcv;

                // Add stock to destination warehouse
                $dst = Stock::firstOrCreate(
                    ['tenant_id' => $transfer->tenant_id, 'warehouse_id' => $transfer->destination_warehouse_id,
                     'product_id' => $line->product_id, 'variant_id' => $line->variant_id],
                    ['quantity' => 0, 'reserved_quantity' => 0, 'unit_cost_cents' => 0, 'total_value_cents' => 0]
                );

                if ($rcv > 0) {
                    $this->stock->moveIn(
                        $dst, $rcv, StockMovement::REASON_TRANSFER,
                        $transfer->number,
                        "Transfert depuis " . $transfer->sourceWarehouse?->name,
                        $receivedBy,
                        $line->unit_cost_cents_at_transfer,
                    );
                }

                $lineStatus = $disc === 0 ? 'received' : ($disc > 0 ? 'partial' : 'received');

                $line->update([
                    'quantity_received'    => $rcv,
                    'quantity_discrepancy' => $disc,
                    'line_status'          => $lineStatus,
                    'discrepancy_reason'   => $disc > 0 ? "Reçu {$rcv}/{$line->quantity_shipped}" : null,
                ]);

                if ($disc > 0) $hasDisc = true;
            }

            $transfer->update([
                'status'      => $hasDisc ? 'partial' : 'received',
                'received_by' => $receivedBy,
                'received_at' => now(),
                'completed_at'=> $hasDisc ? null : now(),
            ]);
        });
    }

    // ── 4. RESOLVE DISPUTE (partial|disputed → completed) ───────────────────

    public function resolveDispute(
        StockTransfer $transfer, string $resolvedBy,
        string $resolution, string $reason
    ): void {
        $this->assertState($transfer, ['partial','disputed'], 'resolveDispute');

        DB::transaction(function () use ($transfer, $resolvedBy, $resolution, $reason) {
            foreach ($transfer->lines->where('line_status', 'partial') as $line) {
                $missing = $line->quantity_discrepancy;

                if ($resolution === 'restock_source' && $missing > 0) {
                    $src = Stock::where('tenant_id', $transfer->tenant_id)
                        ->where('warehouse_id', $transfer->source_warehouse_id)
                        ->where('product_id', $line->product_id)
                        ->when($line->variant_id, fn ($q) => $q->where('variant_id', $line->variant_id), fn ($q) => $q->whereNull('variant_id'))
                        ->first();
                    if ($src) {
                        $this->stock->moveIn($src, $missing, 'return',
                            $transfer->number, 'Litige transfert — retour source', $resolvedBy);
                    }
                } elseif ($resolution === 'write_off' && $missing > 0) {
                    // Write-off: the goods were lost in transit.
                    // Use adjust() to properly record the loss via StockService.
                    // Look up source stock first; fall back to destination stock.
                    $writeOffStock = Stock::where('tenant_id', $transfer->tenant_id)
                        ->where('warehouse_id', $transfer->source_warehouse_id)
                        ->where('product_id', $line->product_id)
                        ->when($line->variant_id, fn ($q) => $q->where('variant_id', $line->variant_id), fn ($q) => $q->whereNull('variant_id'))
                        ->first()
                        ?? Stock::where('tenant_id', $transfer->tenant_id)
                            ->where('warehouse_id', $transfer->destination_warehouse_id)
                            ->where('product_id', $line->product_id)
                            ->when($line->variant_id, fn ($q) => $q->where('variant_id', $line->variant_id), fn ($q) => $q->whereNull('variant_id'))
                            ->first();

                    if ($writeOffStock) {
                        $currentQty = $writeOffStock->fresh()->quantity;
                        $this->stock->adjust(
                            $writeOffStock,
                            max(0, $currentQty - $missing),
                            'write_off',
                            'Litige TRF perte',
                            $resolvedBy,
                            $transfer->number
                        );
                    }
                }
                $line->update(['line_status' => 'resolved', 'discrepancy_reason' => $reason]);
            }

            $transfer->update([
                'status'                 => 'completed',
                'dispute_resolved_by'    => $resolvedBy,
                'dispute_resolved_at'    => now(),
                'dispute_resolution'     => "{$resolution}: {$reason}",
                'completed_at'           => now(),
            ]);
        });
    }

    private function assertState(StockTransfer $t, array $allowed, string $action): void
    {
        if (! in_array($t->status, $allowed, true)) {
            throw new \DomainException("Impossible d'exécuter '{$action}' sur un transfert en état '{$t->status}'.");
        }
    }
}
