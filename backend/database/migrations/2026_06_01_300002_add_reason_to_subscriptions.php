<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Sprint 11 — Persist suspension reason that was previously lost (parameter accepted but never stored). */
return new class extends Migration {
    public function up(): void {
        Schema::table('subscriptions', function (Blueprint $t) {
            $t->text('suspension_reason')->nullable()->after('cancelled_at');
        });
    }

    public function down(): void {
        Schema::table('subscriptions', function (Blueprint $t) {
            $t->dropColumn('suspension_reason');
        });
    }
};
