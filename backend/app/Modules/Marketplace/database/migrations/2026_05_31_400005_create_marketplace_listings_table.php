<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P2 — Links tenant SKUs to external marketplace listings.
 * Platforms: shopify, woocommerce, jumia, amazon, facebook, whatsapp_catalog, tiktok...
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_listings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('product_id')->index();
            $table->uuid('variant_id')->nullable()->index();
            $table->uuid('warehouse_id')->nullable(); // optionally scope to a warehouse

            // External platform
            // Byte budget for unique index (MySQL utf8mb4 limit: 1000 bytes total):
            //   tenant_id (CHAR 36) = 144 bytes
            //   platform  (50)      = 200 bytes
            //   ext_product_id (80) = 320 bytes
            //   ext_variant_id (80) = 320 bytes   → total = 984 bytes ✓
            $table->string('platform', 50);
            $table->string('external_product_id', 80);
            $table->string('external_variant_id', 80)->nullable();
            $table->string('external_sku', 100)->nullable();
            $table->string('external_url', 512)->nullable();

            // Sync status
            $table->string('sync_status', 30)->default('active');
            // active | paused | closed | error | pending_manual | syncing
            $table->timestamp('last_synced_at')->nullable();
            $table->json('last_sync_error')->nullable();
            $table->unsignedSmallInteger('sync_retry_count')->default(0);
            $table->unsignedInteger('sync_error_count')->default(0);

            // Automation flags
            $table->boolean('is_auto_close_enabled')->default(false);
            $table->boolean('is_auto_reopen_enabled')->default(false);
            $table->unsignedSmallInteger('close_threshold')->default(0); // close when qty <= threshold
            $table->boolean('is_price_sync_enabled')->default(false);

            // Platform-specific config (OAuth tokens, page IDs, catalog IDs, etc.)
            $table->json('platform_config')->nullable();

            // Soft delete
            $table->softDeletes();
            $table->timestamps();

            $table->unique(
                ['tenant_id', 'platform', 'external_product_id', 'external_variant_id'],
                'ml_tenant_platform_ext_unique'
            );
            $table->index(['tenant_id', 'platform', 'sync_status'], 'ml_tenant_platform_status_idx');
            $table->index(['variant_id', 'platform'],              'ml_variant_platform_idx');
            $table->index(['product_id', 'platform'],              'ml_product_platform_idx');
        });
    }

    public function down(): void { Schema::dropIfExists('marketplace_listings'); }
};
