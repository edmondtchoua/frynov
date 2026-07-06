<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P1 — entité « demande de changement de plan » de premier plan.
 *
 * Le système était PAIEMENT-first (`manual_payments`) : le changement de plan n'existait pas comme
 * objet avec son cycle de vie. Cette table le matérialise (machine à états), fige un SNAPSHOT des
 * conditions du plan cible au moment de la demande (les modifs ultérieures du plan ne s'appliquent
 * pas rétroactivement) et référence les paiements manuels comme PIÈCES de la demande.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_change_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('from_plan_id')->nullable()->constrained('plans'); // plan courant (snapshot d'origine)
            $table->foreignUuid('to_plan_id')->constrained('plans');               // plan demandé

            $table->string('interval', 16)->default('monthly');       // monthly|yearly
            $table->unsignedInteger('quantity')->default(1);          // nb de périodes payées d'avance (P0.1)
            $table->string('market_code', 32)->nullable();            // marché résolu serveur-side
            $table->string('currency', 8)->default('XOF');
            $table->string('change_type', 24)->default('upgrade');    // upgrade|downgrade|crossgrade|periodicity|reactivation
            $table->string('effective', 16)->default('immediate');    // immediate|next_cycle (différé — P4)
            $table->string('status', 24)->default('draft');           // machine à états (voir le modèle)

            // ── Snapshot du devis AUTORITATIF (P0) ────────────────────────────────────────────────
            $table->unsignedBigInteger('base_gross_minor')->default(0);
            $table->string('promo_code', 32)->nullable();
            $table->unsignedBigInteger('promo_discount_minor')->default(0);
            $table->unsignedBigInteger('proration_credit_minor')->default(0);
            $table->unsignedBigInteger('net_payable_minor')->default(0);

            // ── Snapshot immuable des conditions du plan cible + historique des transitions ─────────
            $table->json('plan_snapshot')->nullable();
            $table->json('metadata')->nullable();

            $table->string('notes', 1000)->nullable();
            $table->foreignUuid('requested_by')->nullable()->constrained('users');
            $table->foreignUuid('reviewed_by')->nullable()->constrained('users');
            $table->string('review_notes', 1000)->nullable();         // note interne admin
            $table->string('rejection_reason', 255)->nullable();

            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status']);
            $table->index('status');
        });

        // Un paiement manuel devient une PIÈCE d'une demande (rétro-compatible : les lignes existantes
        // restent NULL, l'ancien flux paiement-first continue de fonctionner).
        Schema::table('manual_payments', function (Blueprint $table) {
            $table->foreignUuid('change_request_id')->nullable()->after('plan_id')
                ->constrained('subscription_change_requests')->nullOnDelete();
            $table->index('change_request_id');
        });
    }

    public function down(): void
    {
        Schema::table('manual_payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('change_request_id');
        });
        Schema::dropIfExists('subscription_change_requests');
    }
};
