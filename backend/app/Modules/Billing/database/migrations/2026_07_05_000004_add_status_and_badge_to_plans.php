<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * P5 — cycle de vie éditorial des plans côté back-office : `status` (active|draft|archived) et `badge`
 * d'affichage (popular|recommended|enterprise|coming_soon|promo|…). Modifier/archiver un plan ne casse
 * PAS les abonnements existants (les demandes figent déjà un `plan_snapshot`, cf. P1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->string('status', 16)->default('active')->after('is_public'); // active|draft|archived
            $table->string('badge', 24)->nullable()->after('status');            // étiquette d'affichage
        });

        // Les plans existants (is_active) restent 'active'. Cohérence explicite.
        DB::table('plans')->where('is_active', true)->update(['status' => 'active']);
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn(['status', 'badge']);
        });
    }
};
