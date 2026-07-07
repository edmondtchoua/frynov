<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RC-25/26 (P2 compta) — ÉCRITURES en partie double + OUTBOX du moteur d'imputation.
 *
 * Invariants (imposés par EntryService, vérifiés par tests) :
 *  - Σ débits = Σ crédits par écriture ; chaque ligne porte EXACTEMENT un côté > 0 ;
 *  - une écriture `posted` est IMMUABLE (correction = extourne liée reversal_of_id) ;
 *  - aucune écriture datée dans une période verrouillée/close.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('journal_id')->index();
            $table->uuid('period_id')->nullable()->index();
            $table->uuid('fiscal_year_id')->nullable();
            $table->string('number', 24)->nullable();            // assigné au POST (séquence par journal)
            $table->date('entry_date');
            $table->string('label');
            $table->char('currency', 3)->default('XOF');
            $table->string('status', 12)->default('draft');      // draft | posted | reversed
            // Traçabilité : origine métier + règle + inputs (rejouable/explicable)
            $table->string('source_type', 64)->nullable();       // Order, Payment, OrderReturn, CashRegisterSession, CashMovement…
            $table->uuid('source_id')->nullable();
            $table->string('rule_code', 48)->nullable();         // règle d'imputation appliquée
            $table->json('inputs_snapshot')->nullable();
            // Extourne
            $table->uuid('reversal_of_id')->nullable();
            $table->uuid('reversed_by_id')->nullable();
            $table->uuid('created_by')->nullable();
            $table->uuid('posted_by')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'number'], 'acc_entries_tenant_number_unique');
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'source_type', 'source_id'], 'acc_entries_source_idx');
        });

        Schema::create('accounting_entry_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('entry_id')->index();
            $table->uuid('account_id')->index();
            $table->string('label')->nullable();
            $table->unsignedBigInteger('debit_minor')->default(0);
            $table->unsignedBigInteger('credit_minor')->default(0);
            $table->uuid('tax_id')->nullable();
            $table->string('third_party_type', 32)->nullable();  // customer | supplier
            $table->uuid('third_party_id')->nullable();
            $table->timestamps();
        });

        // Outbox du moteur : un événement métier = une ligne, UNIQUE par source → idempotence
        // du rejeu (resync offline, retry worker). `entry_id` posé une fois l'écriture générée.
        Schema::create('accounting_outbox', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->string('event_type', 48);                    // pos.sale, pos.refund, pos.session_gap, payment.recorded, cash.movement
            $table->string('source_type', 64);
            $table->uuid('source_id');
            $table->json('payload');
            $table->string('status', 16)->default('pending');    // pending | processed | failed | skipped
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->string('last_error', 500)->nullable();
            $table->uuid('entry_id')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'event_type', 'source_type', 'source_id'], 'acc_outbox_unique');
            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_outbox');
        Schema::dropIfExists('accounting_entry_lines');
        Schema::dropIfExists('accounting_entries');
    }
};
