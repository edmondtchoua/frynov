<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RC-6A — OUTBOX d'envoi : chaque notification rendue est journalisée puis expédiée en asynchrone
 * (`notifications:flush-outbox`, retry borné). Sert aussi de journal consultable dans la SPA.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_outbox', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('channel_id')->nullable();   // canal résolu à l'émission

            $table->string('channel', 16);            // email | sms | whatsapp
            $table->string('template_code', 64)->nullable();
            $table->string('recipient', 190);         // email ou numéro
            $table->string('subject', 190)->nullable();
            $table->text('body');                     // corps RENDU (placeholders résolus)

            $table->string('status', 16)->default('pending'); // pending | sent | failed
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('sent_at')->nullable();

            $table->timestamps();

            $table->index(['tenant_id', 'status'], 'notif_outbox_tenant_status_idx');
            $table->index(['status', 'attempts'], 'notif_outbox_flush_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_outbox');
    }
};
