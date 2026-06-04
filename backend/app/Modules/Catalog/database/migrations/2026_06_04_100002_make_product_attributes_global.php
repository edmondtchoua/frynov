<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 17 — Allow tenant-global attributes (product_id nullable).
 *
 * Global attributes (product_id IS NULL) are shared across all products of a tenant.
 * Product-specific attributes (product_id NOT NULL) remain scoped to one product.
 *
 * Also adds an `is_global` boolean for explicit flagging and a `use_count` helper.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_attributes', function (Blueprint $table) {
            // Make product_id nullable — global attributes have no product_id
            $table->dropForeign(['product_id']);
            $table->dropUnique(['product_id', 'code']);

            $table->uuid('product_id')->nullable()->change();
            $table->boolean('is_global')->default(false)->after('position');
            $table->unsignedInteger('use_count')->default(0)->after('is_global')
                  ->comment('How many products use this attribute — denormalized counter');

            // Re-add FK nullable-aware
            $table->foreign('product_id')
                  ->references('id')->on('products')
                  ->onDelete('cascade')
                  ->nullable();

            // Allow multiple products to share the same code at tenant level
            $table->unique(['product_id', 'code'], 'product_attrs_product_code_unique');
            $table->index(['tenant_id', 'is_global'], 'product_attrs_tenant_global_idx');
        });
    }

    public function down(): void
    {
        Schema::table('product_attributes', function (Blueprint $table) {
            $table->dropIndex('product_attrs_tenant_global_idx');
            $table->dropUnique('product_attrs_product_code_unique');
            $table->dropForeign(['product_id']);
            $table->dropColumn(['is_global', 'use_count']);
            $table->uuid('product_id')->nullable(false)->change();
            $table->unique(['product_id', 'code']);
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
        });
    }
};
