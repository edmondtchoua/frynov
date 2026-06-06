<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('tenants', function (Blueprint $t) {
            $t->boolean('onboarded')->default(false)->after('status');
        });
        // Backfill: existing tenants are already onboarded
        DB::table('tenants')->update(['onboarded' => true]);
    }
    public function down(): void {
        Schema::table('tenants', function (Blueprint $t) { $t->dropColumn('onboarded'); });
    }
};
