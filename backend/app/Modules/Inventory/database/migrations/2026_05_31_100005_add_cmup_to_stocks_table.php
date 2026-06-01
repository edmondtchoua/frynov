<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds CMUP (Coût Moyen Unitaire Pondéré / Weighted Average Cost) tracking
 * directly on the stocks table for real-time financial valuation.
 *
 * unit_cost_cents  : current CMUP per unit (recalculated on each moveIn)
 * total_value_cents: denormalized total value = quantity * unit_cost_cents
 *
 * Bootstrapped from products.cost_amount for existing rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stocks', function (Blueprint $table) {
            $table->unsignedBigInteger('unit_cost_cents')->default(0)->after('low_stock_threshold');
            $table->unsignedBigInteger('total_value_cents')->default(0)->after('unit_cost_cents');
        });

        // Bootstrap existing stock rows from product cost_amount.
        // Uses a subquery form compatible with both MySQL and SQLite.
        DB::statement("
            UPDATE stocks
            SET
                unit_cost_cents = (
                    SELECT COALESCE(NULLIF(cost_amount, 0), price_amount, 0)
                    FROM products
                    WHERE products.id = stocks.product_id
                ),
                total_value_cents = quantity * (
                    SELECT COALESCE(NULLIF(cost_amount, 0), price_amount, 0)
                    FROM products
                    WHERE products.id = stocks.product_id
                )
            WHERE unit_cost_cents = 0
        ");
    }

    public function down(): void
    {
        Schema::table('stocks', function (Blueprint $table) {
            $table->dropColumn(['unit_cost_cents', 'total_value_cents']);
        });
    }
};
