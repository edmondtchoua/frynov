<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * P0 — Adds warehouse_id to stocks for multi-location inventory.
 * Migrates existing stock rows to a "default" warehouse auto-created per tenant.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Step 1: add nullable warehouse_id
        Schema::table('stocks', function (Blueprint $table) {
            $table->uuid('warehouse_id')->nullable()->after('variant_id');
            $table->index(['warehouse_id', 'product_id'], 'stocks_wh_product_idx');
            $table->index(['tenant_id', 'warehouse_id', 'product_id'], 'stocks_tenant_wh_product_idx');
        });

        if (DB::getDriverName() !== 'sqlite') {
            // Step 2: create default warehouse for every existing tenant
            DB::statement("
                INSERT INTO warehouses (id, tenant_id, name, code, type, is_default, is_active, currency, created_at, updated_at)
                SELECT UUID(), s.tenant_id, 'Entrepôt principal', 'WH-DEFAULT', 'warehouse', 1, 1, 'XOF', NOW(), NOW()
                FROM (SELECT DISTINCT tenant_id FROM stocks) AS s
                ON DUPLICATE KEY UPDATE id = id
            ");

            // Step 3: link existing stocks to their tenant's default warehouse
            DB::statement("
                UPDATE stocks s
                JOIN warehouses w ON w.tenant_id = s.tenant_id AND w.is_default = 1
                SET s.warehouse_id = w.id
                WHERE s.warehouse_id IS NULL
            ");
        }

        // Step 4: add unique constraint now that data is migrated
        Schema::table('stocks', function (Blueprint $table) {
            $table->unique(
                ['tenant_id', 'warehouse_id', 'product_id', 'variant_id'],
                'stocks_warehouse_sku_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('stocks', function (Blueprint $table) {
            $table->dropUnique('stocks_warehouse_sku_unique');
            $table->dropIndex('stocks_tenant_wh_product_idx');
            $table->dropIndex('stocks_wh_product_idx');
            $table->dropColumn('warehouse_id');
        });
    }
};
