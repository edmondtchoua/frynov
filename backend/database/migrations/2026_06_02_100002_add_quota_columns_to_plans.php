<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Sprint 12 — Africa-specific quota dimensions missing from plans table. */
return new class extends Migration {
    public function up(): void {
        Schema::table('plans', function (Blueprint $t) {
            $t->unsignedInteger('max_agents')->nullable()->after('max_monthly_orders');
            $t->unsignedInteger('max_branches')->nullable()->after('max_agents');
            $t->unsignedInteger('max_warehouses')->nullable()->after('max_branches');
        });
    }

    public function down(): void {
        Schema::table('plans', function (Blueprint $t) {
            $t->dropColumn(['max_agents', 'max_branches', 'max_warehouses']);
        });
    }
};
