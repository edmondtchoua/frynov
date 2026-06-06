<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('temporary_access_grants', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('tenant_id')->index();
            $t->uuid('user_id')->index();
            $t->string('role');                  // tenant role granted temporarily
            $t->uuid('granted_by')->nullable();
            $t->timestamp('expires_at')->index();
            $t->timestamp('revoked_at')->nullable();
            $t->string('note', 255)->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('temporary_access_grants');
    }
};
