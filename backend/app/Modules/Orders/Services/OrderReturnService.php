<?php

namespace App\Modules\Orders\Services;

use App\Modules\Inventory\Models\Stock;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Services\PeriodLockService;
use App\Modules\Inventory\Services\StockService;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderReturn;
use App\Modules\Orders\Models\OrderReturnLine;
use Illuminate\Support\Facades\DB;

/**
 * Sprint 10 — Order return / RMA service.
 * State machine: pending → approved → restocked (+ optional stock replenishment)
 * On restock: only 'resalable' items are re-added to stock via StockService::moveIn().
 */
class OrderReturnService
{
    public function __construct(private readonly StockService $stocks) {}

    // ── 1. CREATE (pending) ───────────────────────────────────────────────

    public function create(
        Order   $order,
        array   $lines,      // [['order_line_id','quantity','reason','condition']]
        string  $reason,
        string  $requestedBy,
        ?string $customerNote = null,
        string  $resolution  = OrderReturn::RESOLUTION_REFUND,
    ): OrderReturn {
        if ($order->status === 'cancelled') {
            throw new \DomainException('Impossible de créer un retour sur une commande annulée.');
        }

        return DB::transaction(function () use ($order, $lines, $reason, $requestedBy, $customerNote, $resolution) {
            $count  = OrderReturn::withoutTenantScope()
                ->where('tenant_id', $order->tenant_id)
                ->withTrashed()->count();

            $return = OrderReturn::create([
                'tenant_id'      => $order->tenant_id,
                'order_id'       => $order->id,
                'number'         => 'RET-' . str_pad($count + 1, 6, '0', STR_PAD_LEFT),
                'status'         => OrderReturn::STATUS_PENDING,
                'reason'         => $reason,
                'resolution'     => $resolution,
                'customer_note'  => $customerNote,
                'requested_by'   => $requestedBy,
                'refund_currency' => $order->currency ?? 'XOF',
            ]);

            foreach ($lines as $l) {
                $orderLine = $order->lines()->findOrFail($l['order_line_id']);
                $return->lines()->create([
                    'order_line_id'      => $orderLine->id,
                    'product_id'         => $orderLine->product_id,
                    'variant_id'         => $orderLine->variant_id,
                    'quantity_requested' => (int) $l['quantity'],
                    'condition'          => $l['condition'] ?? 'resalable',
                    'reason'             => $l['reason'] ?? $reason,
                    'unit_price_cents'   => $orderLine->unit_price_cents,
                ]);
            }

            return $return->load('lines');
        });
    }

    // ── 2. APPROVE ────────────────────────────────────────────────────────

    public function approve(
        OrderReturn $return,
        string $approvedBy,
        array  $approvedQtys = [],
        ?string $internalNote = null,
    ): void {
        $this->assertState($return, [OrderReturn::STATUS_PENDING], 'approve');

        DB::transaction(function () use ($return, $approvedBy, $approvedQtys, $internalNote) {
            $refundTotal = 0;

            foreach ($return->lines as $line) {
                $qty = (int) ($approvedQtys[$line->id] ?? $line->quantity_requested);
                $qty = min($qty, $line->quantity_requested);
                $line->update(['quantity_approved' => $qty]);
                $refundTotal += $qty * $line->unit_price_cents;
            }

            $return->update([
                'status'              => OrderReturn::STATUS_APPROVED,
                'approved_by'         => $approvedBy,
                'approved_at'         => now(),
                'refund_amount_cents' => $refundTotal,
                'internal_note'       => $internalNote,
            ]);
        });
    }

    // ── 3. RESTOCK (approved → restocked) ────────────────────────────────

    public function restock(
        OrderReturn $return,
        string $processedBy,
        ?string $warehouseId = null,
    ): void {
        $this->assertState($return, [OrderReturn::STATUS_APPROVED, OrderReturn::STATUS_PROCESSING], 'restock');

        // Period lock guard (same protection as forward movements)
        PeriodLockService::assertOperationAllowed($return->tenant_id);

        DB::transaction(function () use ($return, $processedBy, $warehouseId) {
            foreach ($return->lines->where('quantity_approved', '>', 0) as $line) {
                // Only resalable items are returned to stock
                if ($line->condition !== 'resalable') {
                    continue;
                }

                $stock = $this->resolveStock(
                    $return->tenant_id,
                    $line->product_id,
                    $line->variant_id,
                    $warehouseId,
                );

                $this->stocks->moveIn(
                    $stock,
                    $line->quantity_approved,
                    StockMovement::REASON_RETURN,
                    $return->number,
                    "Retour {$return->number} — {$return->reason}",
                    $processedBy,
                    $line->unit_price_cents,
                );

                $line->update(['quantity_restocked' => $line->quantity_approved]);
            }

            $return->update([
                'status'       => OrderReturn::STATUS_RESTOCKED,
                'processed_by' => $processedBy,
                'restocked_at' => now(),
            ]);
        });
    }

    // ── 4. REJECT ────────────────────────────────────────────────────────

    public function reject(OrderReturn $return, string $rejectedBy, string $reason): void
    {
        $this->assertState($return, [OrderReturn::STATUS_PENDING, OrderReturn::STATUS_APPROVED], 'reject');

        $return->update([
            'status'           => OrderReturn::STATUS_REJECTED,
            'rejected_at'      => now(),
            'rejection_reason' => $reason,
            'approved_by'      => $rejectedBy,
        ]);
    }

    // ── Private ───────────────────────────────────────────────────────────

    private function resolveStock(
        string $tenantId,
        string $productId,
        ?string $variantId,
        ?string $warehouseId,
    ): Stock {
        $query = Stock::withoutTenantScope()
            ->where('tenant_id',  $tenantId)
            ->where('product_id', $productId)
            ->when(
                $variantId,
                fn ($q) => $q->where('variant_id', $variantId),
                fn ($q) => $q->whereNull('variant_id'),
            );

        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        } else {
            $query->whereHas('warehouse', fn ($q) => $q->where('is_default', true));
        }

        return $query->firstOrFail();
    }

    private function assertState(OrderReturn $r, array $allowed, string $action): void
    {
        if (! in_array($r->status, $allowed, true)) {
            throw new \DomainException(
                "Impossible d'exécuter '{$action}' sur un retour en état '{$r->status}'."
            );
        }
    }
}
