<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->nullable()->index();     // internal user who acted
            $table->uuid('tenant_id')->nullable()->index();   // affected tenant (null = global)
            $table->string('action');                         // e.g. module.activated, subscription.suspended
            $table->string('subject_type')->nullable();       // model class name
            $table->uuid('subject_id')->nullable();           // model PK
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->text('notes')->nullable();
            // No soft deletes — audit logs are immutable
            $table->timestamp('created_at')->useCurrent();

            $table->index(['action', 'created_at']);
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
