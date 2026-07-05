<?php
namespace App\Modules\Inventory\Services;

use App\Modules\Inventory\Models\FiscalPeriod;
use App\Modules\Inventory\Models\InventorySnapshot;
use Illuminate\Support\Facades\DB;

/**
 * Axe 4 — Period lock service.
 * Guard clause pattern: assertOperationAllowed() MUST be called before any StockMovement.
 * Double protection: applicative check + MySQL trigger.
 */
class PeriodLockService
{
    /**
     * Guard clause: throws DomainException if date is inside a locked period.
     * Call before every StockMovement creation.
     */
    public static function assertOperationAllowed(string $tenantId, ?string $date = null): void
    {
        $date ??= now()->toDateString();

        $locked = FiscalPeriod::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->where('status', 'locked')
            ->where('starts_at', '<=', $date)
            ->where('ends_at',   '>=', $date)
            ->exists();

        if ($locked) {
            throw new \DomainException(
                "Opération refusée : la période comptable couvrant le {$date} est verrouillée. " .
                "Contactez votre responsable financier."
            );
        }
    }

    /** Transition: open → review */
    public function startReview(FiscalPeriod $period): void
    {
        if ($period->status !== 'open') {
            throw new \DomainException("Seule une période 'open' peut passer en révision.");
        }
        $period->update(['status' => 'review']);
    }

    /**
     * Transition: open|review → locked (IRREVERSIBLE).
     * Calculates and stores HMAC integrity hash.
     */
    public function lock(FiscalPeriod $period, string $lockedBy, string $reason): void
    {
        if ($period->isLocked()) {
            throw new \DomainException("Cette période est déjà verrouillée.");
        }

        DB::transaction(function () use ($period, $lockedBy, $reason) {
            // Total stock value as of period end
            $totalValue = DB::table('stocks')
                ->where('tenant_id', $period->tenant_id)
                ->sum('total_value_cents');

            $hash = hash_hmac('sha256', json_encode([
                'tenant_id'   => $period->tenant_id,
                'starts_at'   => $period->starts_at->toDateString(),
                'ends_at'     => $period->ends_at->toDateString(),
                'total_value' => $totalValue,
                'locked_by'   => $lockedBy,
            ], JSON_UNESCAPED_UNICODE), config('app.key'));

            $period->update([
                'status'                    => 'locked',
                'locked_by'                 => $lockedBy,
                'locked_at'                 => now(),
                'lock_reason'               => $reason,
                'total_value_cents_at_lock' => $totalValue,
                'integrity_hash'            => $hash,
            ]);

            // Finalize all snapshots in this period
            DB::table('inventory_snapshots')
                ->where('tenant_id', $period->tenant_id)
                ->whereBetween('period_start', [
                    $period->starts_at->toDateString(),
                    $period->ends_at->toDateString(),
                ])
                ->update(['is_finalized' => true]);
        });
    }

    /** Verify HMAC integrity — detects retroactive tampering. */
    public function verifyIntegrity(FiscalPeriod $period): bool
    {
        if (! $period->isLocked() || ! $period->integrity_hash) return true;

        $expected = hash_hmac('sha256', json_encode([
            'tenant_id'   => $period->tenant_id,
            'starts_at'   => $period->starts_at->toDateString(),
            'ends_at'     => $period->ends_at->toDateString(),
            'total_value' => $period->total_value_cents_at_lock,
            'locked_by'   => $period->locked_by,
        ], JSON_UNESCAPED_UNICODE), config('app.key'));

        return hash_equals($expected, $period->integrity_hash);
    }
}
