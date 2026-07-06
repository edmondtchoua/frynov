<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RC-39 (P4.2 compta) — LETTRAGE des comptes de tiers.
 *
 * Un lettrage rapproche des lignes débit/crédit d'un MÊME compte formant un groupe équilibré
 * (Σ débits = Σ crédits) : elles partagent un `lettrage_code` (A, B, C… par compte). Ce qui reste
 * non lettré = le solde réellement ouvert (factures non réglées, règlements non affectés).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounting_entry_lines', function (Blueprint $table) {
            $table->string('lettrage_code', 8)->nullable()->after('credit_minor');
            $table->timestamp('lettered_at')->nullable()->after('lettrage_code');
            $table->index(['account_id', 'lettrage_code']);
        });
    }

    public function down(): void
    {
        Schema::table('accounting_entry_lines', function (Blueprint $table) {
            $table->dropIndex(['account_id', 'lettrage_code']);
            $table->dropColumn(['lettrage_code', 'lettered_at']);
        });
    }
};
