<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Physical inventory session management (annual + cycle count / inventaire tournant).
 *
 * Sessions have a lifecycle: open → counting → reviewing → closed.
 * Each session has lines (one per SKU) with theoretical and counted quantities.
 * Adjustments are created only after admin validation (dual-approval principle).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->string('type', 20)->default('cycle'); // annual | cycle
            // NULL = all products; A/B/C = ABC class filter for cycle count
            $table->string('abc_class', 1)->nullable();
            // open | counting | reviewing | closed | cancelled
            $table->string('status', 20)->default('open');
            $table->uuid('created_by');
            $table->uuid('validated_by')->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });

        Schema::create('inventory_session_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('session_id')->index();
            $table->uuid('stock_id');
            $table->uuid('product_id');
            $table->uuid('variant_id')->nullable();
            $table->string('sku', 100)->nullable();
            $table->string('product_name')->nullable();
            // Quantity at session creation time (hidden from counter to avoid bias)
            $table->integer('theoretical_qty');
            // Actual counted quantity (null until counted)
            $table->integer('counted_qty')->nullable();
            // Second count (triggered when |delta| > threshold)
            $table->integer('recount_qty')->nullable();
            // Final validated delta
            $table->integer('delta')->nullable();
            // pending | counted | recounting | validated | adjusted
            $table->string('status', 20)->default('pending');
            $table->uuid('counted_by')->nullable();
            $table->timestamp('counted_at')->nullable();
            $table->timestamps();

            $table->foreign('session_id')->references('id')->on('inventory_sessions')->onDelete('cascade');
            $table->index(['session_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_session_lines');
        Schema::dropIfExists('inventory_sessions');
    }
};
