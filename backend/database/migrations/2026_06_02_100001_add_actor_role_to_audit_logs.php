<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Sprint 12 — Add actor_role to audit_logs for post-incident privilege level analysis. */
return new class extends Migration {
    public function up(): void {
        Schema::table('audit_logs', function (Blueprint $t) {
            $t->string('actor_role', 50)->nullable()->after('user_id');
            $t->string('risk_level', 20)->nullable()->after('actor_role');
            $t->string('request_id', 36)->nullable()->after('risk_level');
        });
    }

    public function down(): void {
        Schema::table('audit_logs', function (Blueprint $t) {
            $t->dropColumn(['actor_role', 'risk_level', 'request_id']);
        });
    }
};
