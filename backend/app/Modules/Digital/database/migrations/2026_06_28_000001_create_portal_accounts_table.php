<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * RC-7C (Phase 3 — comptes clients) — compte PORTAIL du client final (email global + mot de passe),
 * TROISIÈME mode d'accès en plus du jeton et du lien magique. Découplé du multi-tenant : le
 * rapprochement se fait par email (un client peut avoir des achats chez plusieurs vendeurs).
 * L'email doit être VÉRIFIÉ par code avant toute connexion (sinon n'importe qui revendiquerait
 * l'email d'autrui et verrait ses achats).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portal_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('email', 190)->unique();
            $table->string('password');                       // hashé
            $table->string('verification_code', 10)->nullable();
            $table->timestamp('verification_expires_at')->nullable();
            $table->timestamp('verified_at')->nullable();     // null = non vérifié → login refusé
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
        });

        // Modèle global du code de vérification (envoyé via le canal d'un tenant connaissant l'email).
        DB::table('notification_templates')->insert([
            'id' => (string) Str::uuid(), 'tenant_id' => null,
            'code' => 'portal.verify_code', 'channel' => 'email', 'locale' => 'fr',
            'subject' => 'Votre code de vérification : {{code}}',
            'body' => "Bonjour,\n\nVotre code de vérification pour l'espace de téléchargement est : {{code}}\nIl expire dans 30 minutes.\n\nSi vous n'êtes pas à l'origine de cette demande, ignorez ce message.",
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_accounts');
        DB::table('notification_templates')->whereNull('tenant_id')->where('code', 'portal.verify_code')->delete();
    }
};
