<?php
namespace App\Modules\Inventory\Jobs;

use App\Modules\Inventory\Models\InventorySnapshot;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Axe 3 — Generates daily inventory snapshots for every (tenant, warehouse, SKU).
 * Pattern: Materialized View refresh — runs nightly at 00:15 via scheduler.
 * Performance: uses DB aggregation + chunk upsert (500/batch).
 */
class InventorySnapshotJob implements ShouldQueue
{
    use Dispatchable, Queueable, InteractsWithQueue;

    public int $timeout = 900;   // 15 min max
    public int $tries   = 2;

    public function __construct(
        private readonly ?string $tenantId = null,
        private readonly string  $forDate  = '',   // 'Y-m-d', default yesterday
    ) {
        $this->onQueue('snapshots');
    }

    public function handle(): void
    {
        $date = $this->forDate ?: now()->subDay()->toDateString();

        $tenants = DB::table('stocks')
            ->when($this->tenantId, fn ($q) => $q->where('tenant_id', $this->tenantId))
            ->select('tenant_id')
            ->distinct()
            ->pluck('tenant_id');

        foreach ($tenants as $tenantId) {
            $this->generateForTenant($tenantId, $date);
        }
    }

    private function generateForTenant(string $tenantId, string $date): void
    {
        // Aggregate movements of the day per (warehouse, product, variant)
        $rows = DB::table('stock_movements as sm')
            ->join('stocks as s', 'sm.stock_id', '=', 's.id')
            ->where('sm.tenant_id', $tenantId)
            ->whereDate('sm.created_at', $date)
            ->select([
                's.warehouse_id',
                'sm.product_id',
                'sm.variant_id',
                DB::raw("SUM(CASE WHEN sm.type='in'         THEN sm.quantity ELSE 0 END) AS total_in"),
                DB::raw("SUM(CASE WHEN sm.type='out'        THEN sm.quantity ELSE 0 END) AS total_out"),
                DB::raw("SUM(CASE WHEN sm.type='adjustment' THEN sm.quantity ELSE 0 END) AS total_adjusted"),
                DB::raw("SUM(CASE WHEN sm.reason='transfer' AND sm.type='in'  THEN sm.quantity ELSE 0 END) AS transferred_in"),
                DB::raw("SUM(CASE WHEN sm.reason='transfer' AND sm.type='out' THEN sm.quantity ELSE 0 END) AS transferred_out"),
            ])
            ->groupBy('s.warehouse_id', 'sm.product_id', 'sm.variant_id')
            ->get();

        // Build upsert records in 500-row chunks
        $rows->chunk(500)->each(function ($chunk) use ($tenantId, $date) {
            $inserts = $chunk->map(function ($r) use ($tenantId, $date) {
                $stock = DB::table('stocks')->where([
                    'tenant_id'    => $tenantId,
                    'warehouse_id' => $r->warehouse_id,
                    'product_id'   => $r->product_id,
                    'variant_id'   => $r->variant_id,
                ])->first();

                return [
                    'id'                          => (string) Str::uuid(),
                    'tenant_id'                   => $tenantId,
                    'warehouse_id'                => $r->warehouse_id,
                    'product_id'                  => $r->product_id,
                    'variant_id'                  => $r->variant_id,
                    'period_type'                 => 'daily',
                    'period_start'                => $date,
                    'period_end'                  => $date,
                    'closing_quantity'            => (int) ($stock?->quantity ?? 0),
                    'closing_reserved_quantity'   => (int) ($stock?->reserved_quantity ?? 0),
                    'closing_unit_cost_cents'     => (int) ($stock?->unit_cost_cents ?? 0),
                    'closing_total_value_cents'   => (int) ($stock?->total_value_cents ?? 0),
                    'total_in'                    => (int) $r->total_in,
                    'total_out'                   => (int) $r->total_out,
                    'total_adjusted'              => (int) $r->total_adjusted,
                    'total_transferred_in'        => (int) $r->transferred_in,
                    'total_transferred_out'       => (int) $r->transferred_out,
                    'is_finalized'                => false,
                    'generated_at'                => now()->toDateTimeString(),
                    'created_at'                  => now()->toDateTimeString(),
                    'updated_at'                  => now()->toDateTimeString(),
                ];
            })->toArray();

            DB::table('inventory_snapshots')->upsert(
                $inserts,
                ['tenant_id','warehouse_id','product_id','variant_id','period_start','period_type'],
                ['closing_quantity','closing_total_value_cents','total_in','total_out',
                 'total_adjusted','total_transferred_in','total_transferred_out',
                 'is_finalized','generated_at','updated_at']
            );
        });
    }
}
