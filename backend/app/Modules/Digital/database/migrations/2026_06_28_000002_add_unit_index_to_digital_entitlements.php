<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RC-7D — droit d'accès PAR EXEMPLAIRE. Une ligne de qty N accorde désormais N entitlements
 * (un jeton / une clé chacun) au lieu d'un seul par ligne. `unit_index` (1..N) trace le rang de
 * l'exemplaire au sein de sa ligne — utilisé pour la révocation au prorata des retours partiels.
 *
 * Les accès existants (1 par ligne) sont réputés être l'exemplaire n°1 (valeur par défaut).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('digital_entitlements', function (Blueprint $table) {
            $table->unsignedInteger('unit_index')->default(1)->after('order_line_id');
        });
    }

    public function down(): void
    {
        Schema::table('digital_entitlements', function (Blueprint $table) {
            $table->dropColumn('unit_index');
        });
    }
};
