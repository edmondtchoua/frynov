<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds integrity_hash column to audit_logs for tamper-detection.
 * Each row stores an HMAC-SHA256 of its content chained with the previous hash.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->string('integrity_hash', 64)->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropColumn('integrity_hash');
        });
    }
};
