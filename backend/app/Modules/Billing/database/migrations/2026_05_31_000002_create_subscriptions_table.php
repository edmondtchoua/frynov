<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('plan_id');
            // trialing | active | past_due | suspended | cancelled | pending_approval
            $table->string('status')->default('trialing');
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->uuid('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('plan_id')->references('id')->on('plans');
        });

        // Also add a helper column on tenants for quick access
        if (! Schema::hasColumn('tenants', 'subscription_status')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->string('subscription_status')->default('trialing')->after('status');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
        Schema::table('tenants', function (Blueprint $table) {
            if (Schema::hasColumn('tenants', 'subscription_status')) {
                $table->dropColumn('subscription_status');
            }
        });
    }
};
