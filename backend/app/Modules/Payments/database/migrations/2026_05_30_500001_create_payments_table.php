<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('order_id')->nullable()->index();   // null = standalone payment
            $table->integer('amount_cents');                  // always positive
            $table->string('currency', 3)->default('EUR');
            $table->enum('method', [
                'cash',
                'mobile_money',
                'card',
                'transfer',
                'cheque',
            ]);
            $table->string('reference')->nullable();          // Mobile Money TxID, cheque #, etc.
            $table->text('note')->nullable();
            $table->timestamp('paid_at')->useCurrent();
            $table->uuid('performed_by')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('orders')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
