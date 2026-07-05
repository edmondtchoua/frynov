<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RC-7E — journal append-only des mouvements de crédits de communication (traçabilité comptable).
 * `delta` > 0 (recharge / remboursement) ou < 0 (envoi) ; `balance_after` fige le solde résultant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('communication_credit_movements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->string('channel', 16);
            $table->bigInteger('delta');             // +recharge/+remboursement, -envoi
            $table->bigInteger('balance_after');
            $table->string('reason', 24);            // recharge | send | refund | adjustment
            $table->string('reference', 191)->nullable(); // id outbox (envoi) ou réf. paiement (recharge)
            $table->json('meta')->nullable();        // pack, prix encaissé, etc.
            $table->uuid('created_by')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'channel'], 'ccm_tenant_channel_idx');
            $table->index(['tenant_id', 'reason'], 'ccm_tenant_reason_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communication_credit_movements');
    }
};
