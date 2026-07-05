<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RC-12 F-5 — invitations d'équipe par email : à la création d'un membre, un code d'activation est
 * envoyé par email (au lieu d'un mot de passe temporaire transmis en réponse API). Le membre définit
 * lui-même son mot de passe. Une invitation en cours par utilisateur.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_invitations', function (Blueprint $table) {
            $table->uuid('user_id')->primary();
            $table->uuid('tenant_id')->index();
            $table->string('code_hash');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->uuid('invited_by')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_invitations');
    }
};
