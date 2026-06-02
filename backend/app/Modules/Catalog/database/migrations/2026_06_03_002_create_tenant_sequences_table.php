<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('tenant_sequences', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('tenant_id')->index();
            $t->string('sequence_key', 50);
            $t->string('prefix', 20)->default('');
            $t->string('pattern', 100)->default('{PREFIX}-{SEQ}');
            $t->unsignedBigInteger('next_number')->default(1);
            $t->unsignedTinyInteger('padding')->default(6);
            $t->json('metadata')->nullable();
            $t->timestamps();
            $t->unique(['tenant_id', 'sequence_key'], 'tenant_sequences_unique');
        });
    }
    public function down(): void { Schema::dropIfExists('tenant_sequences'); }
};
