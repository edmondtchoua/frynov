<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('stock_id')->index();
            $table->uuid('product_id')->index();
            $table->uuid('variant_id')->nullable();
            // 'in' | 'out' | 'adjustment' | 'return'
            $table->string('type', 20);
            $table->unsignedInteger('quantity');
            $table->integer('quantity_before');
            $table->integer('quantity_after');
            // delivery | sale | loss | return | count | manual
            $table->string('reason', 50);
            $table->string('reference', 100)->nullable();
            $table->text('note')->nullable();
            $table->uuid('performed_by')->nullable();
            $table->timestamps();

            $table->foreign('stock_id')->references('id')->on('stocks')->onDelete('cascade');
            $table->index(['stock_id', 'created_at']);
            $table->index(['tenant_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
