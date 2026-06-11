<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RC-5D — rattache une politique de garantie à un produit (nullable : la plupart des produits n'en ont
 * pas). À la vente d'un produit qui en porte une, un contrat de garantie est généré automatiquement.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'warranty_policy_id')) {
                $table->uuid('warranty_policy_id')->nullable()->after('fulfillment_type');
                $table->index(['tenant_id', 'warranty_policy_id'], 'products_tenant_warranty_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'warranty_policy_id')) {
                $table->dropIndex('products_tenant_warranty_idx');
                $table->dropColumn('warranty_policy_id');
            }
        });
    }
};
