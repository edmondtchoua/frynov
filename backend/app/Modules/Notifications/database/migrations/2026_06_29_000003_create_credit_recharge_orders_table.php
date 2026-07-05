<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RC-7F — commande de recharge de crédits de communication payable par Mobile Money.
 * Le tenant crée la commande (référence unique payable), le webhook du fournisseur la confirme :
 * la recharge est alors appliquée automatiquement (sans intervention de l'opérateur).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_recharge_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();

            $table->string('reference', 32);          // référence payable (RCH-XXXXXXXX), globalement unique
            $table->string('pack_code', 64);
            $table->string('channel', 16);            // snapshot du pack (email | sms | whatsapp)
            $table->bigInteger('credits');            // snapshot : crédits achetés
            $table->bigInteger('price_cents');        // snapshot : montant attendu (mineur)
            $table->string('currency', 3);

            // pending | paid | cancelled | needs_review
            $table->string('status', 16)->default('pending');
            $table->string('provider', 64)->nullable();      // fournisseur ayant confirmé
            $table->string('provider_ref', 191)->nullable(); // id de transaction Mobile Money
            $table->timestamp('paid_at')->nullable();
            $table->uuid('movement_id')->nullable();         // mouvement de crédit appliqué
            $table->json('meta')->nullable();                // payload/incident (montant divergent…)

            $table->uuid('created_by')->nullable();
            $table->timestamps();

            $table->unique('reference', 'credit_recharge_orders_reference_unique');
            $table->index(['tenant_id', 'status'], 'credit_recharge_orders_tenant_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_recharge_orders');
    }
};
