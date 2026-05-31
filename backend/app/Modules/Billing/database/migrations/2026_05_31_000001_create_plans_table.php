<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code')->unique();            // starter, pro, enterprise
            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('price_monthly_cents')->default(0);
            $table->integer('price_yearly_cents')->default(0);
            $table->string('currency')->default('XOF');
            $table->integer('max_users')->nullable();    // null = unlimited
            $table->integer('max_products')->nullable();
            $table->integer('max_monthly_orders')->nullable();
            $table->integer('trial_days')->default(14);
            $table->json('features')->nullable();        // array of feature strings
            $table->boolean('is_active')->default(true);
            $table->boolean('is_public')->default(true); // visible to prospects
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
