<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create("user_warehouses", function (Blueprint $t) {
            $t->uuid("id")->primary();
            $t->uuid("user_id")->index();
            $t->uuid("warehouse_id")->index();
            $t->uuid("tenant_id")->index();
            $t->string("role", 20)->default("operator");
            $t->timestamps();
            $t->unique(["user_id","warehouse_id"], "user_warehouse_unique");
        });
    }
    public function down(): void { Schema::dropIfExists("user_warehouses"); }
};
