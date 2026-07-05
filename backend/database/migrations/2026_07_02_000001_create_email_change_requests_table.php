<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RC-11 F-6 — changement d'email vérifié : le nouvel email n'est appliqué qu'après confirmation d'un
 * code envoyé à CETTE nouvelle adresse. Une demande en cours par utilisateur.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_change_requests', function (Blueprint $table) {
            $table->uuid('user_id')->primary();
            $table->string('new_email');
            $table->string('code_hash');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_change_requests');
    }
};
