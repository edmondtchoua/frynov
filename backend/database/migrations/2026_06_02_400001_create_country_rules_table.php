<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create("country_rules", function (Blueprint $t) {
            $t->uuid("id")->primary();
            $t->string("country_code", 2)->unique();
            $t->boolean("is_active")->default(true);
            $t->boolean("requires_approval")->default(false);
            $t->boolean("is_blocked")->default(false);
            $t->json("allowed_plans")->nullable();
            $t->string("default_currency", 3)->nullable();
            $t->string("default_timezone", 50)->nullable();
            $t->json("metadata")->nullable();
            $t->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists("country_rules"); }
};
