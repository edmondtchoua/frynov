<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stock adjustment requests with dual-approval workflow.
 *
 * Adjustments whose absolute value (|delta| * unit_cost) exceeds the tenant's
 * configured threshold require admin approval before execution.
 * Small adjustments execute immediately (status = approved).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_adjustment_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('stock_id');
            $table->uuid('product_id')->index();
            $table->uuid('variant_id')->nullable();
            $table->integer('quantity_before');
            $table->integer('quantity_requested');
            $table->integer('delta');                      // computed: requested - before
            $table->unsignedBigInteger('value_cents')->default(0); // |delta| * unit_cost
            // loss | count | manual | damage | theft | correction | donation
            $table->string('reason', 50);
            $table->text('note')->nullable();
            // pending | approved | rejected | executed
            $table->string('status', 20)->default('pending')->index();
            $table->uuid('requested_by');
            $table->uuid('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->foreign('stock_id')->references('id')->on('stocks')->onDelete('cascade');
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'requested_by']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_adjustment_requests');
    }
};
