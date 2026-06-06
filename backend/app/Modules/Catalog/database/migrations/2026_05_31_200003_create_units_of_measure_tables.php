<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units_of_measure', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->string('code', 20);
            $table->string('name', 50);
            $table->string('symbol', 10)->nullable();
            $table->string('category', 20)->default('unit');
            $table->boolean('is_base_unit')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['tenant_id', 'code']);
        });

        Schema::create('uom_conversions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('from_uom_id');
            $table->uuid('to_uom_id');
            $table->decimal('factor', 15, 6);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['tenant_id', 'from_uom_id', 'to_uom_id']);
            $table->foreign('from_uom_id')->references('id')->on('units_of_measure')->onDelete('cascade');
            $table->foreign('to_uom_id')->references('id')->on('units_of_measure')->onDelete('cascade');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->uuid('stock_uom_id')->nullable()->after('has_variants');
            $table->uuid('sale_uom_id')->nullable()->after('stock_uom_id');
            $table->uuid('purchase_uom_id')->nullable()->after('sale_uom_id');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['stock_uom_id', 'sale_uom_id', 'purchase_uom_id']);
        });
        Schema::dropIfExists('uom_conversions');
        Schema::dropIfExists('units_of_measure');
    }
};
