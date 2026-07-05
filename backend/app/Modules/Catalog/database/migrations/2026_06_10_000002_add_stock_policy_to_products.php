<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * RC-5A (socle produits spéciaux) — politique serveur de stock & de livraison, distincte de la
 * nature commerciale (`product_type`) :
 *  - `stock_tracking`   : none | aggregate | batch | serialized  (comment le stock est suivi) ;
 *  - `fulfillment_type` : none | manual | delivery | download | license | appointment  (comment on livre).
 *
 * Cette séparation (audit produits-spéciaux §6.1) évite de mélanger « ce que c'est », « comment on
 * compte le stock » et « comment on livre ». Elle est la fondation du non-stockable fiable (services,
 * digital) et du sérialisé (IMEI/VIN) à venir.
 *
 * Backfill : les services existants deviennent non stockables (`none` / `manual`). Les autres produits
 * gardent le défaut `aggregate` / `delivery` (comportement historique inchangé).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'stock_tracking')) {
                $table->string('stock_tracking', 16)
                      ->default('aggregate')
                      ->after('product_type')
                      ->comment('none|aggregate|batch|serialized');
            }
            if (! Schema::hasColumn('products', 'fulfillment_type')) {
                $table->string('fulfillment_type', 16)
                      ->default('delivery')
                      ->after('stock_tracking')
                      ->comment('none|manual|delivery|download|license|appointment');
            }
            $table->index(['tenant_id', 'stock_tracking'], 'products_tenant_stock_tracking_idx');
        });

        // Backfill : un service n'a pas de stock et se « livre » par une action manuelle.
        DB::table('products')
            ->where('product_type', 'service')
            ->update(['stock_tracking' => 'none', 'fulfillment_type' => 'manual']);
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_tenant_stock_tracking_idx');
            if (Schema::hasColumn('products', 'fulfillment_type')) {
                $table->dropColumn('fulfillment_type');
            }
            if (Schema::hasColumn('products', 'stock_tracking')) {
                $table->dropColumn('stock_tracking');
            }
        });
    }
};
