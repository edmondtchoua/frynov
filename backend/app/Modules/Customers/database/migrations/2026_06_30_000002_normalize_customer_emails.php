<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * RC-8 F-12 — normalise (minuscules + trim) les emails clients existants, pour aligner les données
 * avec le mutateur `Customer::setEmailAttribute` et fiabiliser le rapprochement des comptes portail
 * (« mes achats »). Idempotent : ré-exécutable sans effet sur des données déjà normalisées.
 */
return new class extends Migration
{
    public function up(): void
    {
        // LOWER(TRIM(email)) fonctionne sous MySQL, PostgreSQL et SQLite.
        DB::table('customers')
            ->whereNotNull('email')
            ->update(['email' => DB::raw('LOWER(TRIM(email))')]);
    }

    public function down(): void
    {
        // Irréversible (la casse d'origine n'est pas conservée) — no-op.
    }
};
