<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RC-6G (règle 1) — LEDGER d'avoirs par tenant (remplace `subscriptions.metadata['overpaid_minor']`).
 * Chaque ligne est un mouvement signé (crédit > 0, consommation < 0) dans UNE devise — un avoir ne
 * franchit jamais une devise (décision RC-2). Le solde = SUM(amount_minor) par (tenant, currency).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_credits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();

            $table->char('currency', 3);
            $table->bigInteger('amount_minor');            // signé : +avoir / -consommation
            $table->string('source', 32);                  // overpaid | proration | consumption | adjustment
            $table->string('reference', 190)->nullable();  // paiement / abonnement d'origine
            $table->uuid('created_by')->nullable();

            $table->timestamps();

            $table->index(['tenant_id', 'currency'], 'tenant_credits_tenant_currency_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_credits');
    }
};
