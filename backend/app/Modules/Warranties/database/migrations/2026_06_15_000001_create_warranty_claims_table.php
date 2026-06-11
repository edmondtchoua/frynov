<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RC-5F (produits spéciaux — SAV) — réclamation rattachée à un contrat de garantie. Trace le cycle
 * d'un retour SAV : ouverture (motif/description client), diagnostic, résolution (réparation /
 * remplacement / remboursement / rejet). Retrouve la vente, le client et — si sérialisé — l'unité.
 *
 * Hors période : une réclamation sur un contrat expiré n'est acceptée qu'avec un override explicite
 * (tracé `out_of_warranty=true` + audit).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warranty_claims', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();

            $table->uuid('warranty_contract_id');
            // Dénormalisés depuis le contrat pour la recherche/affichage SAV.
            $table->uuid('customer_id')->nullable();
            $table->uuid('inventory_unit_id')->nullable();

            $table->string('reason', 32);                 // defect | breakage | malfunction | other
            $table->text('description')->nullable();      // plainte client
            $table->text('diagnostic')->nullable();       // diagnostic technicien

            // open | in_repair | resolved | replaced | rejected
            $table->string('status', 16)->default('open');
            $table->string('resolution', 32)->nullable(); // repair | replacement | refund | rejected
            $table->text('resolution_note')->nullable();

            $table->boolean('out_of_warranty')->default(false); // ouverte hors période via override

            $table->timestamp('opened_at');
            $table->timestamp('resolved_at')->nullable();
            $table->uuid('opened_by')->nullable();
            $table->uuid('resolved_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status'], 'warranty_claims_tenant_status_idx');
            $table->index(['tenant_id', 'warranty_contract_id'], 'warranty_claims_tenant_contract_idx');
            $table->index(['tenant_id', 'customer_id'], 'warranty_claims_tenant_customer_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warranty_claims');
    }
};
