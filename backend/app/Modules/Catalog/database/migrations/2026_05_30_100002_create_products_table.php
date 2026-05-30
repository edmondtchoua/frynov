<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('category_id')->nullable()->index();
            $table->string('sku');
            $table->string('name');
            $table->text('description')->nullable();
            // Price stored as centimes (integer) to avoid float precision issues
            $table->unsignedBigInteger('price_amount');
            $table->char('price_currency', 3)->default('XOF');
            $table->unsignedBigInteger('compare_at_price_amount')->nullable();
            $table->unsignedBigInteger('cost_amount')->nullable();
            $table->string('status')->default('draft'); // draft | active | archived
            $table->boolean('has_variants')->default(false);
            $table->string('barcode')->nullable();        // EAN-13 or custom barcode
            $table->decimal('weight_kg', 8, 3)->nullable();
            $table->json('metadata')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['tenant_id', 'sku']);
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
