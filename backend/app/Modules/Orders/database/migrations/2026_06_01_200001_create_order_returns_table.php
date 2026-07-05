<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 10 — Order Returns (RMA flow).
 * State machine: pending → approved → restocked | rejected | cancelled
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('order_returns', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('tenant_id')->index();
            $t->uuid('order_id');
            $t->string('number', 30)->unique();
            $t->string('status', 25)->default('pending');
            $t->string('reason', 50)->nullable();
            $t->text('customer_note')->nullable();
            $t->text('internal_note')->nullable();
            $t->string('resolution', 30)->nullable();
            $t->bigInteger('refund_amount_cents')->default(0);
            $t->string('refund_currency', 3)->default('XOF');
            $t->uuid('requested_by')->nullable();
            $t->uuid('approved_by')->nullable();
            $t->uuid('processed_by')->nullable();
            $t->timestamp('approved_at')->nullable();
            $t->timestamp('restocked_at')->nullable();
            $t->timestamp('rejected_at')->nullable();
            $t->text('rejection_reason')->nullable();
            $t->timestamps();
            $t->softDeletes();

            $t->index(['tenant_id', 'status'],   'or_tenant_status_idx');
            $t->index(['tenant_id', 'order_id'], 'or_tenant_order_idx');
            $t->foreign('order_id')->references('id')->on('orders');
        });

        Schema::create('order_return_lines', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('return_id')->index();
            $t->uuid('order_line_id');
            $t->uuid('product_id')->index();
            $t->uuid('variant_id')->nullable();
            $t->unsignedInteger('quantity_requested');
            $t->unsignedInteger('quantity_approved')->default(0);
            $t->unsignedInteger('quantity_restocked')->default(0);
            $t->string('condition', 20)->default('resalable');
            $t->string('reason', 50)->nullable();
            $t->bigInteger('unit_price_cents')->default(0);
            $t->timestamps();

            $t->foreign('return_id')->references('id')->on('order_returns')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_return_lines');
        Schema::dropIfExists('order_returns');
    }
};
