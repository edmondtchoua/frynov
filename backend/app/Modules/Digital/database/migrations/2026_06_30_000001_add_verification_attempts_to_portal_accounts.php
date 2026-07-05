<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Recette QA (SEC) — limite les tentatives de vérification du code portail : au-delà d'un seuil, le
 * code est invalidé (force une nouvelle inscription), empêchant le brute-force du code à 6 chiffres.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('portal_accounts', function (Blueprint $table) {
            $table->unsignedSmallInteger('verification_attempts')->default(0)->after('verification_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('portal_accounts', function (Blueprint $table) {
            $table->dropColumn('verification_attempts');
        });
    }
};
