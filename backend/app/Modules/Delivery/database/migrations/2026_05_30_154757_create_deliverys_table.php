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

            // NB: la FK vers `orders` est posée dans une migration ultérieure
            // (2026_07_10_100000_add_order_fk_to_deliveries) car ce fichier
            // trie AVANT create_orders_table (154757 < 300001) et échouerait
            // sur une base vierge (MySQL 1824 « Failed to open referenced table »).
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deliveries');
    }
};
