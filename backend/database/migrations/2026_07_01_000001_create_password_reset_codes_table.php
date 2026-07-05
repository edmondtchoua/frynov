<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RC-10 F-3 — réinitialisation de mot de passe par CODE (6 chiffres) envoyé par email.
 * Une demande par email (clé primaire) ; code haché, expirable, borné en tentatives (anti brute-force).
 * Choix d'un code plutôt qu'un lien : mobile-first (marché cible), cohérent avec le portail.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('password_reset_codes', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('code_hash');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_reset_codes');
    }
};
