<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('products', function (Blueprint $t) {
            $t->string('internal_barcode', 50)->nullable()->after('barcode');
            $t->string('gtin', 20)->nullable()->after('internal_barcode');
            $t->string('barcode_type', 20)->default('INTERNAL')->after('gtin');
            $t->string('barcode_source', 10)->default('AUTO')->after('barcode_type');
            $t->boolean('barcode_auto_generated')->default(false)->after('barcode_source');
            $t->unique(['tenant_id', 'internal_barcode'], 'products_tenant_ibarcode_unique');
        });
    }
    public function down(): void {
        Schema::table('products', function (Blueprint $t) {
            $t->dropUnique('products_tenant_ibarcode_unique');
            $t->dropColumn(['internal_barcode', 'gtin', 'barcode_type', 'barcode_source', 'barcode_auto_generated']);
        });
    }
};
