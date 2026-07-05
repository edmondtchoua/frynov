<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P6-1 — moyens de paiement disponibles par marché (calqué sur plan_prices).
 * Référence plateforme (non tenant-scopée). `mode` = auto (rail PSP réel) | manual
 * (preuve + validation admin via ManualPayment) | quote (sur devis). Matérialise le DoD :
 * « chaque devise affichée correspond à un flux OU à une mention manuel/sur-devis ».
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('market_payment_methods', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('market_code', 32);              // waemu, cemac, europe, canada, usa...
            $table->string('country_code', 2)->nullable();  // override pays (inutilisé au départ)
            $table->string('currency', 3);
            $table->string('method', 32);                   // wave, orange_money, mtn_money, mpesa, bank_transfer, card, cash
            $table->string('mode', 16)->default('manual');  // auto | manual | quote
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('display_order')->default(0);
            $table->string('label')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['market_code', 'country_code', 'method'], 'mpm_market_country_method_unique');
            $table->index(['market_code', 'currency'], 'mpm_market_currency_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_payment_methods');
    }
};
