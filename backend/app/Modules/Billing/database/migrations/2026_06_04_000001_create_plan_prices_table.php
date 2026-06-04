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
            $table->string('market_code');       // waemu, cemac, europe, canada, usa...
            $table->string('country_code')->nullable();
            $table->string('currency', 3);
            $table->string('interval')->default('monthly');
            $table->unsignedInteger('base_amount_minor')->default(0);
            $table->unsignedInteger('included_users')->default(1);
            $table->unsignedInteger('extra_user_amount_minor')->nullable();
            $table->boolean('is_public')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('plan_id')->references('id')->on('plans')->cascadeOnDelete();
            $table->unique(['plan_id', 'market_code', 'interval']);
            $table->index(['market_code', 'currency']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_prices');
    }
};
