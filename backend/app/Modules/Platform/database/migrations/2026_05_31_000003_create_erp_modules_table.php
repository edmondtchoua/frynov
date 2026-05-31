<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('erp_modules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code')->unique();          // catalog, inventory, orders, …
            $table->string('name');
            $table->string('category');                // core | operations | finance | analytics | advanced
            $table->text('description')->nullable();
            $table->text('icon_svg')->nullable();      // SVG path data for the icon
            // active | beta | coming_soon | maintenance | disabled
            $table->string('status')->default('active');
            $table->boolean('is_core')->default(false);    // always active for all tenants
            $table->boolean('is_visible')->default(true);  // shown in module marketplace
            $table->string('route_prefix')->nullable();    // /catalog, /inventory, …
            $table->string('color')->nullable();            // CSS var or hex, e.g. #10b981
            $table->json('metadata')->nullable();          // extra config
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('plan_modules', function (Blueprint $table) {
            $table->uuid('plan_id');
            $table->uuid('module_id');
            $table->boolean('is_included')->default(true);  // false = optional add-on
            $table->json('limits')->nullable();              // {'max_imports_per_month': 10}
            $table->primary(['plan_id', 'module_id']);
            $table->foreign('plan_id')->references('id')->on('plans')->cascadeOnDelete();
            $table->foreign('module_id')->references('id')->on('erp_modules')->cascadeOnDelete();
        });

        Schema::create('tenant_modules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('module_id');
            // active | inactive | suspended | trial
            $table->string('status')->default('active');
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->uuid('activated_by')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'module_id']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('module_id')->references('id')->on('erp_modules')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_modules');
        Schema::dropIfExists('plan_modules');
        Schema::dropIfExists('erp_modules');
    }
};
