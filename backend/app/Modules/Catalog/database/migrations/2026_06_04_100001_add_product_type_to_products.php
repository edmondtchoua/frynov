<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 17 — Add product_type to products.
 *
 * product_type:
 *   simple   — single-SKU product with no variants (default)
 *   variable — product with N-axis variants (has_variants = true)
 *   service  — non-stockable service (no stock movements)
 *   kit      — composed of other products (BOM, Phase 3)
 *
 * The existing `has_variants` boolean is kept for backward compat but
 * product_type is the authoritative type discriminator going forward.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // product_type — simple|variable|service|kit
            if (! Schema::hasColumn('products', 'product_type')) {
                $table->string('product_type', 20)
                      ->default('simple')
                      ->after('has_variants')
                      ->comment('simple|variable|service|kit');
            }
            $table->index(['tenant_id', 'product_type'], 'products_tenant_type_idx');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_tenant_type_idx');
            if (Schema::hasColumn('products', 'product_type')) {
                $table->dropColumn('product_type');
            }
        });
    }
};
