<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Axe 2 — Transferts inter-entrepôts avec gestion du stock en transit.
 * State machine: draft → requested → in_transit → received|partial → completed|disputed → resolved
 */
return new class extends Migration {
    public function up(): void {
        Schema::create('stock_transfers', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('tenant_id')->index();
            $t->string('number', 30)->unique();         // TRF-000001
            $t->uuid('source_warehouse_id');
            $t->uuid('destination_warehouse_id');
            $t->string('status', 25)->default('draft');
            // draft|requested|in_transit|received|partial|disputed|completed|cancelled
            $t->text('notes')->nullable();
            $t->json('metadata')->nullable();           // transporteur, tracking, etc.
            $t->uuid('requested_by');
            $t->uuid('shipped_by')->nullable();
            $t->uuid('received_by')->nullable();
            $t->uuid('dispute_resolved_by')->nullable();
            $t->timestamp('shipped_at')->nullable();
            $t->timestamp('expected_at')->nullable();
            $t->timestamp('received_at')->nullable();
            $t->timestamp('completed_at')->nullable();
            $t->timestamp('disputed_at')->nullable();
            $t->timestamp('dispute_resolved_at')->nullable();
            $t->text('dispute_reason')->nullable();
            $t->text('dispute_resolution')->nullable();
            $t->softDeletes();
            $t->timestamps();

            $t->index(['tenant_id','status'],                       'st_tenant_status_idx');
            $t->index(['tenant_id','source_warehouse_id'],          'st_src_wh_idx');
            $t->index(['tenant_id','destination_warehouse_id'],     'st_dst_wh_idx');
            $t->index(['tenant_id','shipped_at'],                   'st_shipped_idx');

            $t->foreign('source_warehouse_id')->references('id')->on('warehouses');
            $t->foreign('destination_warehouse_id')->references('id')->on('warehouses');
        });

        Schema::create('stock_transfer_lines', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('transfer_id')->index();
            $t->uuid('product_id')->index();
            $t->uuid('variant_id')->nullable();
            $t->unsignedInteger('quantity_requested');
            $t->unsignedInteger('quantity_shipped')->default(0);
            $t->unsignedInteger('quantity_received')->default(0);
            $t->integer('quantity_discrepancy')->default(0);
            $t->string('discrepancy_reason', 255)->nullable();
            $t->string('line_status', 20)->default('pending');
            // pending|shipped|received|partial|disputed|resolved
            $t->bigInteger('unit_cost_cents_at_transfer')->default(0);
            $t->timestamps();

            $t->unique(['transfer_id','product_id','variant_id'], 'stl_transfer_sku_unique');
            $t->foreign('transfer_id')->references('id')->on('stock_transfers')->onDelete('cascade');
        });
    }

    public function down(): void {
        Schema::dropIfExists('stock_transfer_lines');
        Schema::dropIfExists('stock_transfers');
    }
};
