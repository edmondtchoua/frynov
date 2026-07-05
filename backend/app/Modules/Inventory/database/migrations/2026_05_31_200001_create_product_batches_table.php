<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Product lot/batch management with FEFO (First Expired, First Out) support.
 * Enables tracking of expiry dates (DLUO/DLC) and serial numbers per stock item.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_batches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('product_id')->index();
            $table->uuid('variant_id')->nullable();
            $table->string('batch_number', 100);           // lot fournisseur
            $table->string('serial_number', 100)->nullable(); // série unique (electronique, médical)
            $table->date('manufacturing_date')->nullable();
            $table->date('expiry_date')->nullable();        // DLUO/DLC → used for FEFO sort
            $table->unsignedInteger('quantity_initial');
            $table->unsignedInteger('quantity')->default(0); // current stock of this batch
            // active | quarantine | expired | exhausted | recalled
            $table->string('status', 20)->default('active')->index();
            $table->text('notes')->nullable();
            $table->uuid('received_by')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'product_id', 'batch_number'], 'batches_tenant_product_number_unique');
            $table->index(['tenant_id', 'product_id', 'expiry_date']); // FEFO query index
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
        });

        // Link stock_movements to batches for full traceability
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->uuid('batch_id')->nullable()->after('variant_id');
            $table->foreign('batch_id')->references('id')->on('product_batches')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropForeign(['batch_id']);
            $table->dropColumn('batch_id');
        });
        Schema::dropIfExists('product_batches');
    }
};
