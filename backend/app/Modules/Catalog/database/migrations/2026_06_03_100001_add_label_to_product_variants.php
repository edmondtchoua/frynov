<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 16 — Add `label` column to product_variants.
 * Label is the human-readable combined string for multi-axis variants.
 * e.g. "S / Rouge", "XL / Bleu / Coton"
 * Backfill from existing `name` or `attributes` JSON where possible.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $t) {
            // Nullable to allow existing variants without a label
            $t->string('label', 200)->nullable()->after('sku');
        });

        // Backfill: use `name` as label for existing variants that have one
        \Illuminate\Support\Facades\DB::table('product_variants')
            ->whereNotNull('name')
            ->whereNull('label')
            ->update(['label' => \Illuminate\Support\Facades\DB::raw('name')]);
    }

    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $t) {
            $t->dropColumn('label');
        });
    }
};
