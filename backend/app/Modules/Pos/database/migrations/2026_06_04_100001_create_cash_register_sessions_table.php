<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_register_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('warehouse_id')->nullable()->index();   // optional register/location
            $table->string('label')->nullable();                  // e.g. "Caisse 1"
            $table->enum('status', ['open', 'closed'])->default('open')->index();

            // All amounts in integer centimes (×100), like every other money column.
            $table->integer('opening_float_cents')->default(0);   // fond de caisse à l'ouverture
            $table->integer('total_sales_cents')->default(0);     // running sum of POS sales
            $table->integer('cash_sales_cents')->default(0);      // running sum of CASH sales only
            $table->unsignedInteger('sales_count')->default(0);

            // Filled at close:
            $table->integer('expected_cash_cents')->nullable();   // opening_float + cash_sales
            $table->integer('counted_cash_cents')->nullable();    // physically counted by cashier
            $table->integer('difference_cents')->nullable();      // counted - expected (signed)

            $table->uuid('opened_by')->nullable();
            $table->uuid('closed_by')->nullable();
            $table->timestamp('opened_at')->useCurrent();
            $table->timestamp('closed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_register_sessions');
    }
};
