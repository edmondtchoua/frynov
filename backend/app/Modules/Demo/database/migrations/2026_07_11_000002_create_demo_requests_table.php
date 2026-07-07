<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Demandes de démonstration émises par des prospects via le formulaire public.
 *
 * Objet PLATFORM-LEVEL (pas de tenant_id, pas de TenantScope) : géré par l'équipe
 * interne (super-admin) depuis le back-office. Machine à états :
 *   new → pending_review → approved → demo_access_sent → (converted_to_customer | expired)
 *                        ↘ rejected
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demo_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Identité prospect
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email')->index();
            $table->string('phone')->nullable();
            $table->string('company')->nullable();
            $table->string('country', 2)->nullable();

            // Besoin
            $table->string('primary_need')->nullable();
            $table->json('modules')->nullable();          // modules qui intéressent le prospect
            $table->text('message')->nullable();

            // Consentements (RGPD)
            $table->boolean('consent_contact')->default(false);
            $table->boolean('consent_demo_email')->default(false);

            // Traçabilité de la soumission
            $table->string('source')->nullable();          // landing, pricing, footer…
            $table->string('locale', 5)->default('fr');    // langue des emails (fr/en)
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();

            // Machine à états
            $table->string('status')->default('new')->index();
            $table->text('internal_notes')->nullable();
            $table->uuid('reviewed_by')->nullable();       // admin ayant traité
            $table->timestamp('reviewed_at')->nullable();
            $table->string('rejection_reason')->nullable();

            // Provisioning (rempli en P2 à l'approbation)
            $table->uuid('demo_tenant_id')->nullable()->index();
            $table->uuid('demo_user_id')->nullable();
            $table->timestamp('demo_access_expires_at')->nullable()->index();
            $table->timestamp('access_sent_at')->nullable();
            $table->string('access_email_status')->nullable(); // sent | failed
            $table->timestamp('last_demo_login_at')->nullable();

            // Cycle de vie (P4/P5)
            $table->timestamp('reminder_sent_at')->nullable();
            $table->timestamp('ended_email_sent_at')->nullable();
            $table->timestamp('converted_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demo_requests');
    }
};
