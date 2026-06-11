<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RC-5B (produits spéciaux — unités sérialisées) — une ligne par UNITÉ physique identifiée (IMEI,
 * VIN/numéro de châssis, numéro de série…). Complète le stock agrégé : on sait quelles sont les N
 * unités, leur identifiant unique, leur état et leur statut de cycle de vie.
 *
 * `serial_type` est volontairement un STRING libre (pas un enum dur) afin d'accepter des identifiants
 * métier futurs (numéro moteur, MAC, plaque…) sans migration — la stratégie de normalisation par défaut
 * couvre les types inconnus. L'unicité est garantie PAR TENANT sur la valeur normalisée.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_units', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('product_id');
            $table->uuid('variant_id')->nullable();
            $table->uuid('warehouse_id')->nullable();

            $table->string('serial_type', 32);              // imei | vin | serial | custom…
            $table->string('serial_value', 120);            // saisie brute (affichage)
            $table->string('normalized_serial', 120);       // normalisée (recherche + unicité)

            // new | used | refurbished | damaged
            $table->string('condition', 16)->default('new');
            // in_stock | reserved | sold | returned | repair | quarantine | lost | scrapped
            $table->string('status', 16)->default('in_stock')->index();

            $table->timestamp('received_at')->nullable();
            $table->timestamp('sold_at')->nullable();

            // Rattachements (renseignés au fil du cycle de vie — réservation/vente/SAV à venir).
            $table->uuid('order_id')->nullable();
            $table->uuid('order_line_id')->nullable();
            $table->uuid('customer_id')->nullable();
            $table->timestamp('warranty_started_at')->nullable();
            $table->timestamp('warranty_ends_at')->nullable();

            $table->text('notes')->nullable();
            $table->uuid('received_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Unicité d'un identifiant sérialisé PAR TENANT (le même IMEI peut exister chez un autre tenant).
            $table->unique(['tenant_id', 'serial_type', 'normalized_serial'], 'inv_units_tenant_serial_unique');
            $table->index(['tenant_id', 'product_id', 'status'], 'inv_units_tenant_product_status_idx');
            $table->index(['tenant_id', 'normalized_serial'], 'inv_units_tenant_normalized_idx');
            $table->index(['tenant_id', 'order_line_id'], 'inv_units_tenant_orderline_idx');

            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_units');
    }
};
