<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P2b — notifications IN-APP d'abonnement (cloche). Complète l'e-mail (outbox P2) par un fil in-app
 * historisé, marqué lu/non-lu, consommé par le centre de notifications du front.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('change_request_id')->nullable()
                ->constrained('subscription_change_requests')->nullOnDelete();
            $table->string('type', 48);              // submitted|activated|rejected|…
            $table->string('severity', 12)->default('info'); // info|warning|error
            $table->string('title', 160);
            $table->string('body', 500)->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'is_read']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_notifications');
    }
};
