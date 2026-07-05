<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RC-6I (Phase 2I — kits/bundles) — NOMENCLATURE d'un kit : composition en produits/variantes avec
 * quantité par kit. Un kit avec nomenclature est VIRTUEL : la vente réserve et consomme le stock des
 * COMPOSANTS (le kit lui-même n'a pas de stock propre).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kit_components', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();

            $table->uuid('kit_product_id');
            $table->uuid('component_product_id');
            $table->uuid('component_variant_id')->nullable();
            $table->unsignedSmallInteger('quantity')->default(1); // par kit vendu

            $table->timestamps();

            $table->unique(
                ['tenant_id', 'kit_product_id', 'component_product_id', 'component_variant_id'],
                'kit_components_scope_unique',
            );
            $table->foreign('kit_product_id')->references('id')->on('products')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kit_components');
    }
};
