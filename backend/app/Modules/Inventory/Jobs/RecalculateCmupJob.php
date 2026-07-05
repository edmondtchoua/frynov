<?php
namespace App\Modules\Inventory\Jobs;

use App\Modules\Inventory\Models\Stock;
use App\Modules\Inventory\Models\StockMovement;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Axe 1 — Async CMUP recalculation via Event Sourcing replay.
 * Replays all TYPE_IN movements in chronological order to recompute the
 * weighted average cost without blocking the main request thread.
 *
 * Dispatched by: moveInBulk() during CSV/API imports.
 * Queue: 'cmup-recalc' (low priority, isolated from transactions).
 */
class RecalculateCmupJob implements ShouldQueue
{
    use Dispatchable, Queueable, InteractsWithQueue;

    public int $tries   = 3;
    public int $backoff = 15;
    public int $timeout = 120;

    public function __construct(
        private readonly string $stockId,
        private readonly string $tenantId,
    ) {
        $this->onQueue('cmup-recalc');
    }

    public function handle(): void
    {
        $lockKey = "cmup.recalc.{$this->stockId}";
        $lock    = Cache::lock($lockKey, 120);

        if (! $lock->get()) {
            $this->release(15); // Another recalculation running — retry in 15s
            return;
        }

        try {
            $this->replay();
        } finally {
            $lock->release();
        }
    }

    private function replay(): void
    {
        DB::transaction(function () {
            $stock = Stock::withoutTenantScope()
                ->where('id', $this->stockId)
                ->where('tenant_id', $this->tenantId)
                ->lockForUpdate()
                ->firstOrFail();

            // Replay all IN movements chronologically (Event Sourcing)
            $movements = StockMovement::where('stock_id', $this->stockId)
                ->where('type', StockMovement::TYPE_IN)
                ->orderBy('created_at')
                ->get(['quantity', 'unit_cost_cents_snapshot']);

            $runningQty   = 0;
            $runningValue = 0;
            $cmup         = 0;

            foreach ($movements as $m) {
                $cost         = $m->unit_cost_cents_snapshot;
                $entryValue   = $m->quantity * $cost;
                $newQty       = $runningQty + $m->quantity;

                $cmup          = $newQty > 0
                    ? (int) round(($runningValue + $entryValue) / $newQty)
                    : $cost;
                $runningQty   = $newQty;
                $runningValue = $runningQty * $cmup;
            }

            $stock->update([
                'unit_cost_cents'   => $cmup,
                'total_value_cents' => $stock->quantity * $cmup,
            ]);

            // Mark all deferred movements as processed
            StockMovement::where('stock_id', $this->stockId)
                ->where('cmup_deferred', true)
                ->update(['cmup_deferred' => false]);
        });
    }
}
