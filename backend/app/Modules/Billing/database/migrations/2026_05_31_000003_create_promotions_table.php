<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code')->unique();                    // e.g. PROMO20
            $table->string('description')->nullable();
            $table->enum('discount_type', ['percent', 'fixed_cents'])->default('percent');
            $table->unsignedInteger('discount_value');           // % or cents
            $table->json('applicable_plans')->nullable();        // null = all plans
            $table->dateTime('valid_from')->nullable();
            $table->dateTime('valid_until')->nullable();
            $table->unsignedInteger('max_uses')->nullable();     // null = unlimited
            $table->unsignedInteger('current_uses')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('promo_uses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('promotion_id')->constrained('promotions')->cascadeOnDelete();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->timestamp('used_at')->useCurrent();

            $table->unique(['promotion_id', 'tenant_id']); // one use per tenant per promo
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promo_uses');
        Schema::dropIfExists('promotions');
    }
};
