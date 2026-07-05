<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RC-5D — contrat de garantie EFFECTIF, généré à la vente (fulfillment). Lie la garantie au client, à la
 * ligne de commande, au produit/variante et — pour les produits sérialisés — à l'UNITÉ vendue (IMEI/VIN).
 * Source de vérité de « cet appareil est sous garantie jusqu'au … » (base du SAV à venir).
 *
 * La période est figée à l'émission : `starts_at` (date de vente) + durée de la politique → `ends_at`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warranty_contracts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();

            $table->uuid('warranty_policy_id');
            $table->uuid('product_id');
            $table->uuid('variant_id')->nullable();
            $table->uuid('inventory_unit_id')->nullable();   // renseigné si produit sérialisé

            $table->uuid('order_id');
            $table->uuid('order_line_id');
            $table->uuid('customer_id')->nullable();

            $table->string('serial_value', 120)->nullable(); // snapshot IMEI/VIN au moment de la vente
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            // active | expired | void — `expired` est dérivable de ends_at ; `void` sur annulation/retour.
            $table->string('status', 16)->default('active');

            $table->uuid('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'customer_id'], 'warranty_contracts_tenant_customer_idx');
            $table->index(['tenant_id', 'order_id'], 'warranty_contracts_tenant_order_idx');
            $table->index(['tenant_id', 'inventory_unit_id'], 'warranty_contracts_tenant_unit_idx');
            $table->index(['tenant_id', 'status'], 'warranty_contracts_tenant_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warranty_contracts');
    }
};
