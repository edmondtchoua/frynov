<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('customer_id')->nullable()->index();
            $table->string('number', 20)->index();           // ORD-00001
            $table->enum('status', ['draft', 'confirmed', 'fulfilled', 'cancelled'])->default('draft');
            $table->unsignedInteger('total_amount')->default(0); // centimes
            $table->char('currency', 3)->default('XOF');
            $table->text('note')->nullable();
            $table->uuid('performed_by')->nullable();
            $table->timestamp('fulfilled_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['tenant_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
