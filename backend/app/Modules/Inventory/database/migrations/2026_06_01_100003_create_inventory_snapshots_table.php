<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Axe 3 — Materialized view pattern for inventory history.
 * Eliminates full-table-scan aggregations on stock_movements for reports.
 * Generated nightly by InventorySnapshotJob at 00:15.
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('inventory_snapshots', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('tenant_id')->index();
            $t->uuid('warehouse_id');
            $t->uuid('product_id');
            $t->uuid('variant_id')->nullable();
            $t->string('period_type', 10);   // daily|monthly
            $t->date('period_start');
            $t->date('period_end');

            // Closing balances
            $t->integer('closing_quantity')->default(0);
            $t->integer('closing_reserved_quantity')->default(0);
            $t->bigInteger('closing_unit_cost_cents')->default(0);
            $t->bigInteger('closing_total_value_cents')->default(0);

            // Period flows
            $t->integer('total_in')->default(0);
            $t->integer('total_out')->default(0);
            $t->integer('total_adjusted')->default(0);
            $t->integer('total_transferred_in')->default(0);
            $t->integer('total_transferred_out')->default(0);

            // KPIs
            $t->integer('low_stock_alert_count')->default(0);
            $t->integer('stockout_days')->default(0);

            $t->boolean('is_finalized')->default(false); // true = locked by fiscal period
            $t->timestamp('generated_at')->nullable();
            $t->timestamps();

            $t->unique(
                ['tenant_id','warehouse_id','product_id','variant_id','period_start','period_type'],
                'inv_snap_unique'
            );
            $t->index(['tenant_id','period_start','period_type'],  'snap_tenant_period_idx');
            $t->index(['tenant_id','warehouse_id','period_start'], 'snap_wh_period_idx');
        });
    }

    public function down(): void { Schema::dropIfExists('inventory_snapshots'); }
};
