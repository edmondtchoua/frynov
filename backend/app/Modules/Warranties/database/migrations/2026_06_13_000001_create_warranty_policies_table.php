<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RC-5D (produits spéciaux — garanties) — politique de garantie réutilisable, attachable à un produit.
 * Décrit la DURÉE et la couverture ; le contrat effectif (par vente/unité) est créé à la livraison
 * dans `warranty_contracts`. La durée est exprimée en MOIS (unité unique en V1 — cf. audit §6.4).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warranty_policies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();

            $table->string('name', 120);                 // ex. « Garantie constructeur 12 mois »
            $table->unsignedSmallInteger('duration_months'); // durée de couverture, en mois
            $table->text('coverage')->nullable();        // texte libre : ce qui est couvert / exclusions
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'is_active'], 'warranty_policies_tenant_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warranty_policies');
    }
};
