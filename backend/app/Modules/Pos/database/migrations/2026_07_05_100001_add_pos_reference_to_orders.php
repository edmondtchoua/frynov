<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RC-22 (P0 audit intégration) — clé d'idempotence du checkout POS.
 *
 * Le POS (surtout mobile/offline) génère un id client AVANT la tentative d'encaissement et le
 * rejoue tel quel à chaque retry (`X-Idempotency-Key`). Stockée sur la commande, la clé permet au
 * serveur de renvoyer la vente déjà créée au lieu d'en produire une seconde quand la requête a
 * abouti mais que la réponse s'est perdue (timeout réseau). Unique PAR TENANT ; NULL pour les
 * commandes hors POS (MySQL autorise plusieurs NULL dans un index unique).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('pos_reference', 64)->nullable()->after('cash_register_session_id');
            $table->unique(['tenant_id', 'pos_reference'], 'orders_tenant_pos_reference_unique');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique('orders_tenant_pos_reference_unique');
            $table->dropColumn('pos_reference');
        });
    }
};
