<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RC-23 (P1 compta) — RÉFÉRENTIEL comptable SYSCOHADA.
 *
 * Voir docs/architecture/comptabilite-syscohada.md. Toutes les tables tenant portent tenant_id
 * (trait HasTenant côté modèle) ; `accounting_account_classes` est un référentiel GLOBAL
 * (classes SYSCOHADA 1–9, seedées, non modifiables par tenant).
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Classes SYSCOHADA (référentiel global) ────────────────────────────────────────────
        Schema::create('accounting_account_classes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedTinyInteger('code')->unique();        // 1..9
            $table->string('name');                                // « Comptes de capitaux »…
            $table->string('type', 16);                            // bilan | gestion
            $table->timestamps();
        });

        // ── Plan comptable par tenant ─────────────────────────────────────────────────────────
        Schema::create('accounting_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->unsignedTinyInteger('class_code');             // 1..9 (dénormalisé pour tri/filtre)
            $table->string('code', 20);                            // 411, 4111, 57100001…
            $table->string('name');
            $table->uuid('parent_id')->nullable()->index();
            $table->string('kind', 16);                            // asset|liability|equity|revenue|expense
            $table->boolean('is_auxiliary')->default(false);       // compte de tiers individualisé
            $table->string('auxiliary_type', 16)->nullable();      // customer | supplier
            $table->uuid('auxiliary_of_id')->nullable();           // customers.id / suppliers.id
            $table->boolean('is_system')->default(false);          // requis par le moteur, non supprimable
            $table->boolean('is_active')->default(true);
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'code'], 'acc_accounts_tenant_code_unique');
            $table->index(['tenant_id', 'class_code']);
        });

        // ── Journaux ──────────────────────────────────────────────────────────────────────────
        Schema::create('accounting_journals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->string('code', 8);                             // VT, AC, CA, BQ, OD, ST, AV, RG
            $table->string('name');
            $table->string('type', 24);                            // sales|purchases|cash|bank|misc|stock|credit_notes|adjustments
            $table->string('sequence_prefix', 12);                 // préfixe SequenceService (ex. VT26)
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'code'], 'acc_journals_tenant_code_unique');
        });

        // ── Taxes ─────────────────────────────────────────────────────────────────────────────
        Schema::create('accounting_taxes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->string('code', 16);                            // TVA18…
            $table->string('name');
            $table->unsignedInteger('rate_bp');                    // points de base : 1800 = 18 %
            $table->uuid('collected_account_id')->nullable();      // 4431 TVA collectée
            $table->uuid('deductible_account_id')->nullable();     // 4452 TVA déductible
            $table->boolean('is_inclusive')->default(false);       // prix TTC (true) ou HT (false)
            $table->string('country', 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'code'], 'acc_taxes_tenant_code_unique');
        });

        // ── Paramétrage comptable (1 ligne / tenant) ──────────────────────────────────────────
        Schema::create('tenant_accounting_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->unique();
            $table->string('country', 2)->nullable();
            $table->char('currency', 3)->default('XOF');
            $table->unsignedTinyInteger('fiscal_year_start_month')->default(1); // 1..12
            $table->json('numbering_rules')->nullable();           // préfixes FA-/PF-/AV- etc.
            $table->json('default_accounts')->nullable();          // {'@cash':'571', '@sales':'701', …}
            $table->boolean('auto_post')->default(false);          // écritures auto : draft (false) ou posted (true)
            $table->date('locked_until')->nullable();              // verrou global rapide
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
        });

        // ── Exercices & périodes ──────────────────────────────────────────────────────────────
        Schema::create('accounting_fiscal_years', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->string('label', 16);                           // « 2026 »
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('status', 16)->default('open');         // open | closing | closed
            $table->uuid('closed_by')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->uuid('carry_forward_entry_id')->nullable();    // écriture de report à nouveau
            $table->timestamps();

            $table->unique(['tenant_id', 'label'], 'acc_fy_tenant_label_unique');
        });

        Schema::create('accounting_periods', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('fiscal_year_id')->index();
            $table->string('label', 16);                           // « 2026-01 »
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('status', 16)->default('open');         // open | locked | closed
            $table->uuid('locked_by')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->string('lock_reason')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'fiscal_year_id', 'label'], 'acc_periods_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_periods');
        Schema::dropIfExists('accounting_fiscal_years');
        Schema::dropIfExists('tenant_accounting_settings');
        Schema::dropIfExists('accounting_taxes');
        Schema::dropIfExists('accounting_journals');
        Schema::dropIfExists('accounting_accounts');
        Schema::dropIfExists('accounting_account_classes');
    }
};
