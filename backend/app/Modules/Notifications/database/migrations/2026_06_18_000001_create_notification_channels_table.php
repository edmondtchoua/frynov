<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RC-6A (Phase 2A — notifications) — CANAL D'ENVOI configurable PAR TENANT.
 *
 * Un canal = un transport prêt à émettre : email SMTP, ou proxy API générique (agrégateur SMS,
 * WhatsApp Business, passerelle email HTTP…). La `config` (credentials, hôte, URL, clés, template de
 * payload) est CHIFFRÉE au repos (cast `encrypted:array`). `from_name`/`from_address` portent le nom
 * d'expéditeur / l'adresse d'envoi / le sender ID / numéro court selon le canal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_channels', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();

            $table->string('channel', 16);            // email | sms | whatsapp
            $table->string('provider', 32);           // smtp | http_api | log
            $table->string('name', 120);              // libellé d'administration

            $table->text('config')->nullable();       // chiffré (encrypted:array) : host/port/user/pass, url/headers/payload…
            $table->string('from_name', 120)->nullable();     // nom d'expéditeur / titre de notification
            $table->string('from_address', 190)->nullable();  // email d'envoi / sender ID / numéro court

            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);    // canal par défaut pour son type

            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'channel', 'is_active'], 'notif_channels_tenant_channel_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_channels');
    }
};
