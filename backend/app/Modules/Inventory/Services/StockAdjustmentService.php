<?php

namespace App\Modules\Inventory\Services;

use App\Models\User;
use App\Modules\Inventory\Exceptions\StockLockException;
use App\Modules\Inventory\Models\Stock;
use App\Modules\Inventory\Models\StockAdjustmentRequest;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Manages the stock adjustment request workflow:
 *   1. request()  — creates a pending or auto-approved adjustment
 *   2. approve()  — admin approves and executes a pending request
 *   3. reject()   — admin rejects with a reason
 *
 * The approval threshold is configured per tenant via tenant.settings.adjustment_approval_threshold
 * (in absolute value cents: |delta| × unit_cost). Default: 50 000.
 *
 * Adjustments below the threshold are auto-executed immediately.
 */
class StockAdjustmentService
{
    private const DEFAULT_THRESHOLD_CENTS = 50_000_00; // 50 000 XOF in centimes

    public function __construct(private readonly StockService $stockService) {}

    // ── Commands ───────────────────────────────────────────────────────────

    /**
     * Create an adjustment request.
     * If the value exceeds the tenant threshold → status = pending (requires admin approval)
     * Otherwise → auto-approved and executed immediately.
     *
     * @throws \InvalidArgumentException
     */
    public function request(
        Stock   $stock,
        int     $newQuantity,
        string  $reason,
        ?string $note,
        User    $requestedBy,
    ): StockAdjustmentRequest {
        if (! in_array($reason, StockAdjustmentRequest::REASONS, true)) {
            throw new \InvalidArgumentException(
                "Invalid reason '$reason'. Valid: " . implode(', ', StockAdjustmentRequest::REASONS)
            );
        }

        $delta       = $newQuantity - $stock->quantity;
        $unitCost    = $stock->unit_cost_cents ?? 0;
        $valueCents  = abs($delta) * $unitCost;
        $threshold   = $this->getThreshold($stock->tenant_id);
        $needsApproval = $valueCents >= $threshold && $delta !== 0;

        return DB::transaction(function () use (
            $stock, $newQuantity, $delta, $valueCents, $reason, $note,
            $requestedBy, $needsApproval
        ) {
            $req = StockAdjustmentRequest::create([
                'tenant_id'          => $stock->tenant_id,
                'stock_id'           => $stock->id,
                'product_id'         => $stock->product_id,
                'variant_id'         => $stock->variant_id,
                'quantity_before'    => $stock->quantity,
                'quantity_requested' => $newQuantity,
                'delta'              => $delta,
                'value_cents'        => $valueCents,
                'reason'             => $reason,
                'note'               => $note,
                'status'             => $needsApproval
                    ? StockAdjustmentRequest::STATUS_PENDING
                    : StockAdjustmentRequest::STATUS_APPROVED,
                'requested_by' => $requestedBy->id,
                'reviewed_by'  => $needsApproval ? null : $requestedBy->id,
                'reviewed_at'  => $needsApproval ? null : now(),
            ]);

            if (! $needsApproval) {
                $this->execute($req, $requestedBy->id);
            }

            return $req;
        });
    }

    /**
     * Admin approves and executes a pending request.
     *
     * @throws \DomainException
     */
    public function approve(StockAdjustmentRequest $req, User $approver): void
    {
        if (! $req->isPending()) {
            throw new \DomainException("Only pending requests can be approved. Current status: {$req->status}");
        }

        DB::transaction(function () use ($req, $approver) {
            $req->update([
                'status'      => StockAdjustmentRequest::STATUS_APPROVED,
                'reviewed_by' => $approver->id,
                'reviewed_at' => now(),
            ]);

            $this->execute($req, $approver->id);
        });
    }

    /**
     * Admin rejects a pending request with a reason.
     *
     * @throws \DomainException
     */
    public function reject(StockAdjustmentRequest $req, User $approver, string $reason): void
    {
        if (! $req->isPending()) {
            throw new \DomainException("Only pending requests can be rejected. Current status: {$req->status}");
        }

        $req->update([
            'status'           => StockAdjustmentRequest::STATUS_REJECTED,
            'reviewed_by'      => $approver->id,
            'reviewed_at'      => now(),
            'rejection_reason' => $reason,
        ]);
    }

    // ── Queries ────────────────────────────────────────────────────────────

    public function pending(string $tenantId): LengthAwarePaginator
    {
        return StockAdjustmentRequest::where('tenant_id', $tenantId)
            ->where('status', StockAdjustmentRequest::STATUS_PENDING)
            ->with(['product:id,name,sku', 'stock'])
            ->latest()
            ->paginate(20);
    }

    public function history(string $tenantId, array $filters = []): LengthAwarePaginator
    {
        return StockAdjustmentRequest::where('tenant_id', $tenantId)
            ->when(! empty($filters['status']), fn ($q) => $q->where('status', $filters['status']))
            ->with(['product:id,name,sku'])
            ->latest()
            ->paginate(20);
    }

    // ── Private ────────────────────────────────────────────────────────────

    private function execute(StockAdjustmentRequest $req, string $performedBy): void
    {
        $stock = Stock::findOrFail($req->stock_id);

        try {
            $this->stockService->adjust(
                $stock,
                $req->quantity_requested,
                $req->reason,
                $req->note,
                $performedBy,
            );
        } catch (StockLockException $e) {
            throw $e; // propagate — caller should retry
        }

        $req->update(['executed_at' => now(), 'status' => StockAdjustmentRequest::STATUS_EXECUTED]);
    }

    private function getThreshold(string $tenantId): int
    {
        $tenant = \App\Modules\Tenants\Models\Tenant::find($tenantId);
        return (int) ($tenant?->settings['adjustment_approval_threshold'] ?? self::DEFAULT_THRESHOLD_CENTS);
    }
}
