<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P2 — In-app notifications for marketplace sync failures.
 * Created when auto-sync fails and human action is required.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_sync_alerts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('listing_id');
            $table->string('severity', 10)->default('warning'); // info|warning|error|critical
            $table->string('type', 60);    // close_failed|reopen_failed|api_error|quota_exceeded
            $table->text('message');
            $table->json('context')->nullable();  // product name, platform, stock level, error detail
            $table->boolean('is_read')->default(false);
            $table->boolean('requires_action')->default(false);
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'is_read', 'requires_action'], 'msa_tenant_action_idx');
            $table->index(['tenant_id', 'severity'],                    'msa_tenant_severity_idx');
            $table->foreign('listing_id')->references('id')->on('marketplace_listings')->onDelete('cascade');
        });
    }

    public function down(): void { Schema::dropIfExists('marketplace_sync_alerts'); }
};
