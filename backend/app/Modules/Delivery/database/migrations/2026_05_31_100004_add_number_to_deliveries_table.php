<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds an immutable sequential BL (Bon de Livraison) number to deliveries.
 * Format: BL-00001
 * The number is generated atomically via the sku_sequences table and is
 * immutable after creation (enforced at the model level via boot()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->string('number', 20)->nullable()->after('id');
            $table->unique(['tenant_id', 'number'], 'deliveries_tenant_number_unique');
        });
    }

    public function down(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropUnique('deliveries_tenant_number_unique');
            $table->dropColumn('number');
        });
    }
};
