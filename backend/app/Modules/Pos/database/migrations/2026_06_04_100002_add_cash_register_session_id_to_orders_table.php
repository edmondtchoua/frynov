<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Links a POS sale back to the cash-register session that produced it,
            // so closing the session can reconcile its sales. Null = non-POS order.
            $table->uuid('cash_register_session_id')->nullable()->index()->after('warehouse_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('cash_register_session_id');
        });
    }
};
