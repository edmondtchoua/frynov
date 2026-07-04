<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RC-6F (Phase 2F — garanties+) — la durée d'une politique s'exprime désormais en JOURS, MOIS ou
 * ANNÉES (`duration_unit`, défaut `month` = compat RC-5D). `duration_months` est renommé conceptuellement
 * `duration_value` (colonne conservée pour compat, lue comme « valeur dans l'unité »).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warranty_policies', function (Blueprint $table) {
            $table->string('duration_unit', 8)->default('month')->after('duration_months'); // day | month | year
        });
    }

    public function down(): void
    {
        Schema::table('warranty_policies', function (Blueprint $table) {
            $table->dropColumn('duration_unit');
        });
    }
};
