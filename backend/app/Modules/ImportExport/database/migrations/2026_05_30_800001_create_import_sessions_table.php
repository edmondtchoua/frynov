<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('performed_by')->index();

            // What and how
            $table->string('type');                        // products|customers|suppliers
            $table->string('status')->default('draft');    // draft|analyzing|analyzed|awaiting_approval|importing|completed|partial|failed|cancelled
            $table->string('mode')->default('create_update'); // create_only|update_only|create_update|simulate

            // File
            $table->string('original_filename');
            $table->string('stored_path');

            // Row counts (filled during analysis)
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('valid_rows')->default(0);
            $table->unsignedInteger('error_rows')->default(0);
            $table->unsignedInteger('warning_rows')->default(0);
            $table->unsignedInteger('imported_rows')->default(0);
            $table->unsignedInteger('skipped_rows')->default(0);

            // Mapping & result metadata
            $table->json('column_mapping')->nullable();   // { file_col: system_field, ... }
            $table->json('summary')->nullable();          // { created: N, updated: N, ... }
            $table->text('error_message')->nullable();    // global error if processing failed

            // Timestamps
            $table->timestamp('analyzed_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->uuid('approved_by')->nullable();

            $table->softDeletes();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_sessions');
    }
};
