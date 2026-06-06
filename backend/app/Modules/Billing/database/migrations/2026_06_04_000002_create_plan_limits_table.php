<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_limits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('plan_id')->unique();
            $table->unsignedInteger('max_products')->nullable();
            $table->unsignedInteger('max_monthly_orders')->nullable();
            $table->unsignedInteger('max_customers')->nullable();
            $table->unsignedInteger('max_branches')->nullable();
            $table->unsignedInteger('max_warehouses')->nullable();
            $table->unsignedInteger('max_imports_per_month')->nullable();
            $table->unsignedInteger('max_api_calls_per_month')->nullable();
            $table->unsignedInteger('storage_mb')->nullable();
            $table->timestamps();

            $table->foreign('plan_id')->references('id')->on('plans')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_limits');
    }
};
