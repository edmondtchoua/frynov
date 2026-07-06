<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RC-43 (P4.3 compta) — RAPPROCHEMENT BANCAIRE (pointage).
 *
 * `pointed` marque une ligne d'écriture d'un compte de banque comme figurant sur le relevé bancaire.
 * Les lignes NON pointées sont les « en-cours » (chèques non débités, dépôts en transit) qui
 * expliquent l'écart entre le solde comptable et le solde du relevé.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounting_entry_lines', function (Blueprint $table) {
            $table->boolean('pointed')->default(false)->after('lettered_at');
            $table->timestamp('pointed_at')->nullable()->after('pointed');
        });
    }

    public function down(): void
    {
        Schema::table('accounting_entry_lines', function (Blueprint $table) {
            $table->dropColumn(['pointed', 'pointed_at']);
        });
    }
};
