<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_rows', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('session_id')->index();
            $table->foreign('session_id')->references('id')->on('import_sessions')->cascadeOnDelete();

            $table->unsignedInteger('row_number');         // 1-based row index from file
            $table->string('status')->default('pending');  // pending|valid|error|warning|imported|skipped

            // Raw data from file (header → value)
            $table->json('raw_data');

            // Mapped + normalized data after column mapping applied
            $table->json('mapped_data')->nullable();

            // Validation feedback
            $table->json('errors')->nullable();    // [{ field, message }]
            $table->json('warnings')->nullable();  // [{ field, message }]

            // Action decided during analysis
            $table->string('action')->nullable();  // create|update|skip

            // The entity created/updated (nullable until import executed)
            $table->uuid('entity_id')->nullable();

            $table->timestamps();

            $table->index(['session_id', 'status']);
            $table->index(['session_id', 'row_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_rows');
    }
};
