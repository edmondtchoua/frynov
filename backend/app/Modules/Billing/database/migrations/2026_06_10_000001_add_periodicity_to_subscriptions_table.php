<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RC-0 (socle Billing périodicité) — l'abonnement porte désormais sa périodicité et la trace
 * du paiement qui l'a activé :
 *  - `interval` : mensuel|annuel (pilote le calcul de current_period_end) ;
 *  - `currency` / `market_code` : marché/devise du paiement (détection de périodicité, proration) ;
 *  - `amount_paid_minor` : montant réellement encaissé pour la période en cours (acompte/proration).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('interval', 16)->default('monthly')->after('status');
            $table->string('currency', 3)->nullable()->after('interval');
            $table->string('market_code', 32)->nullable()->after('currency');
            $table->unsignedInteger('amount_paid_minor')->nullable()->after('market_code');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn(['interval', 'currency', 'market_code', 'amount_paid_minor']);
        });
    }
};
