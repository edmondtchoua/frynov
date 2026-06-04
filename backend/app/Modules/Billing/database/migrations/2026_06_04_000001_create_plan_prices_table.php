<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_prices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('plan_id');
            $table->string('market_code', 32);   // waemu, cemac, europe, canada, usa...
            $table->string('country_code', 2)->nullable();
            $table->string('currency', 3);
            $table->string('interval', 16)->default('monthly');
            $table->unsignedInteger('base_amount_minor')->default(0);
            $table->unsignedInteger('included_users')->default(1);
            $table->unsignedInteger('extra_user_amount_minor')->nullable();
            $table->boolean('is_public')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('plan_id', 'plan_prices_plan_fk')->references('id')->on('plans')->cascadeOnDelete();
            $table->unique(['plan_id', 'market_code', 'interval'], 'plan_prices_plan_market_interval_unique');
            $table->index(['market_code', 'currency'], 'plan_prices_market_currency_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_prices');
    }
};
