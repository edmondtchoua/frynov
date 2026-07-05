<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deliveries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('order_id')->nullable()->index();
            $table->enum('status', ['pending', 'dispatched', 'in_transit', 'delivered', 'failed'])->default('pending');
            $table->json('address')->nullable();
            $table->string('carrier')->nullable();
            $table->string('tracking_number')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('failed_reason')->nullable();
            $table->uuid('performed_by')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('orders')->nullOnDelete();
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deliveries');
    }
};
