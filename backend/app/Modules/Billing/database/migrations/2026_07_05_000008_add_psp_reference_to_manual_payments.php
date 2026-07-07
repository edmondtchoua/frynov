<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Option PSP — référence du paiement automatisé, pour rapprocher le webhook du paiement. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('manual_payments', function (Blueprint $table) {
            $table->string('psp_reference', 80)->nullable()->after('payment_method');
            $table->index('psp_reference');
        });
    }

    public function down(): void
    {
        Schema::table('manual_payments', function (Blueprint $table) {
            $table->dropIndex(['psp_reference']);
            $table->dropColumn('psp_reference');
        });
    }
};
