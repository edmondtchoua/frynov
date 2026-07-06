<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P2 — recueil de consentement (Phase 7). Trace, de façon immuable et horodatée, l'accord d'un
 * utilisateur (ou saisi par un admin hors plateforme) lors d'un changement de plan : texte accepté,
 * version, IP, user-agent, source, et l'entité liée (généralement une demande de changement).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_consents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users');   // qui a consenti (null si hors plateforme)

            $table->string('action_type', 48);          // ex. plan_change
            $table->text('consent_text');                // texte exact accepté
            $table->string('consent_version', 32);       // version du texte (traçabilité)
            $table->timestamp('accepted_at');
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->string('source', 16)->default('platform'); // platform|admin|email|phone|document|other

            // Entité liée (polymorphe léger) — généralement une SubscriptionChangeRequest.
            $table->string('related_entity_type', 64)->nullable();
            $table->uuid('related_entity_id')->nullable();

            $table->string('comment', 500)->nullable();  // note (consentement manuel admin)
            $table->string('proof_path')->nullable();    // justificatif (consentement manuel)
            $table->foreignUuid('created_by')->nullable()->constrained('users'); // admin ayant saisi (manuel)
            $table->timestamps();

            $table->index(['tenant_id', 'action_type']);
            $table->index(['related_entity_type', 'related_entity_id'], 'sc_related_entity_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_consents');
    }
};
