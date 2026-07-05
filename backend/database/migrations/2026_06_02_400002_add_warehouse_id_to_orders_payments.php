<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table("orders", function (Blueprint $t) {
            $t->uuid("warehouse_id")->nullable()->after("tenant_id");
            $t->index(["tenant_id","warehouse_id"], "orders_tenant_warehouse_idx");
        });
        Schema::table("payments", function (Blueprint $t) {
            $t->uuid("warehouse_id")->nullable()->after("tenant_id");
        });
    }
    public function down(): void {
        Schema::table("orders", fn($t) => $t->dropColumn("warehouse_id"));
        Schema::table("payments", fn($t) => $t->dropColumn("warehouse_id"));
    }
};
