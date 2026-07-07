<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RC-33 (P3.2 compta) — AVOIRS (notes de crédit).
 *
 * Un avoir réutilise la table `invoices` (kind = credit_note, numéro AV-) : mêmes lignes, même
 * moteur d'imputation mais écriture INVERSE à l'émission (débit 701 HT + débit 4431 TVA / crédit
 * 411 client, journal AV). Il peut ensuite être APPLIQUÉ à une facture émise pour en réduire le
 * reste dû (`credit_note_applications`, borné au reste des deux documents).
 *
 * - `invoices.credit_note_of_id` : sur une ligne avoir → la facture d'origine (traçabilité).
 * - `invoices.credited_minor`     : sur une facture → total déjà compensé par des avoirs.
 *   Pour un avoir, `paid_minor` sert de « montant appliqué » ; `remainingMinor()` en tient compte.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->uuid('credit_note_of_id')->nullable()->after('proforma_id')->index();
            $table->unsignedBigInteger('credited_minor')->default(0)->after('paid_minor');
        });

        Schema::create('credit_note_applications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('credit_note_id')->index();   // invoices.id (kind=credit_note)
            $table->uuid('invoice_id')->index();        // invoices.id (kind=invoice) compensée
            $table->unsignedBigInteger('amount_minor');
            $table->uuid('created_by')->nullable();
            $table->timestamps();

            $table->unique(['credit_note_id', 'invoice_id'], 'cn_app_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_note_applications');
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['credit_note_of_id', 'credited_minor']);
        });
    }
};
