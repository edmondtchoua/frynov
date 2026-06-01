<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Axe 1 — Adds unit_cost_cents_snapshot to stock_movements.
 * Enables CMUP replay from movement history (Event Sourcing pattern).
 * Critical for async CMUP recalculation correctness.
 */
return new class extends Migration {
    public function up(): void {
        Schema::table('stock_movements', function (Blueprint $t) {
            // Cost at time of movement — essential for CMUP replay
            $t->bigInteger('unit_cost_cents_snapshot')->default(0)->after('quantity_after');
            // Was this movement included in a CMUP batch recalculation?
            $t->boolean('cmup_deferred')->default(false)->after('unit_cost_cents_snapshot');
        });
    }

    public function down(): void {
        Schema::table('stock_movements', function (Blueprint $t) {
            $t->dropColumn(['unit_cost_cents_snapshot','cmup_deferred']);
        });
    }
};
