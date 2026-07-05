<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RC-1C — détection de périodicité & acompte échelonné sur les paiements manuels.
 *
 * Toutes les colonnes sont ADDITIVES (nullable ou défaut sûr) : aucune réécriture des lignes
 * existantes (les paiements legacy restent valides, `resolution_status` NULL). `amount_cents` reste
 * le montant encaissé en unités MINEURES (XOF/XAF : unité = unité mineure).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('manual_payments', function (Blueprint $table) {
            // Marché résolu serveur-side au submit (clé de cumul d'acompte + étiquette d'audit).
            $table->string('market_code', 32)->nullable()->after('currency');
            // Périodicité DÉCLARÉE par le tenant au submit (repli pour router un acompte). NULL = non déclaré.
            $table->string('declared_interval', 16)->nullable()->after('market_code');
            // Périodicité RETENUE à l'approbation (détectée, sinon déclarée). NULL tant que non traité.
            $table->string('detected_interval', 16)->nullable()->after('declared_interval');
            // Cible nette retenue (trace de calcul). NULL = indéterminé / gratuit / unmatched.
            $table->unsignedInteger('target_amount_minor')->nullable()->after('detected_interval');
            // Reste dû vers la cible APRÈS ce paiement (0 = soldé). Figé pour l'UI/audit.
            $table->unsignedInteger('remaining_due_minor')->default(0)->after('target_amount_minor');
            // Trop-perçu (avoir) constaté à l'approbation. 0 si pas de sur-paiement.
            $table->unsignedInteger('overpaid_minor')->default(0)->after('remaining_due_minor');
            // Statut applicatif : matched|partial|overpaid|free|needs_review|unmatched. NULL avant traitement.
            $table->string('resolution_status', 24)->nullable()->after('overpaid_minor');
            // Horodatage d'IMPUTATION au cumul d'acompte (garde-fou idempotence). NULL = pas encore imputé.
            $table->timestamp('applied_at')->nullable()->after('resolution_status');

            // Cumul des acomptes non soldés d'une même cible (clé STABLE : tenant+plan+market).
            $table->index(['tenant_id', 'plan_id', 'market_code', 'status'], 'mp_deposit_cumul_idx');
        });
    }

    public function down(): void
    {
        Schema::table('manual_payments', function (Blueprint $table) {
            $table->dropIndex('mp_deposit_cumul_idx');
            $table->dropColumn([
                'market_code', 'declared_interval', 'detected_interval',
                'target_amount_minor', 'remaining_due_minor', 'overpaid_minor',
                'resolution_status', 'applied_at',
            ]);
        });
    }
};
