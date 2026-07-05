<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('product_id')->index();
            $table->uuid('tenant_id')->index();
            $table->string('sku');
            $table->string('name')->nullable();           // e.g. "Rouge / L"
            $table->json('attributes')->nullable();       // {"color":"red","size":"L"}
            // Null = inherit parent product price
            $table->unsignedBigInteger('price_amount')->nullable();
            $table->char('price_currency', 3)->nullable();
            $table->unsignedBigInteger('cost_amount')->nullable();
            $table->string('barcode')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['tenant_id', 'sku']);
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
