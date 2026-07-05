<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dedicated SKU sequence counter per (tenant, prefix).
 * Used by SkuGeneratorService to generate collision-free sequential SKUs
 * under concurrent requests via SELECT ... FOR UPDATE.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sku_sequences', function (Blueprint $table) {
            $table->uuid('tenant_id');
            $table->string('prefix', 10);
            $table->unsignedBigInteger('last_seq')->default(0);
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->primary(['tenant_id', 'prefix']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sku_sequences');
    }
};
