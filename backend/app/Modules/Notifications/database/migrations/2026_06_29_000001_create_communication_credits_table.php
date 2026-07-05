<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RC-7E — solde de crédits de communication par tenant × canal (email/SMS/WhatsApp).
 * Une ligne par couple (tenant, canal). Le solde est décompté à l'envoi et abondé à la recharge.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('communication_credits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->string('channel', 16);           // email | sms | whatsapp
            $table->bigInteger('balance')->default(0); // nombre d'envois restants (jamais négatif)
            $table->timestamps();

            $table->unique(['tenant_id', 'channel'], 'communication_credits_tenant_channel_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communication_credits');
    }
};
