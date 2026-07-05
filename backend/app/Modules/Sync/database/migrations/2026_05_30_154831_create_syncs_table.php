<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
Schema::create('syncs', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->uuid('tenant_id')->index();
    // TODO: ajouter les colonnes du module
    $table->softDeletes();
    $table->timestamps();
});
    }

    public function down(): void
    {
Schema::dropIfExists('syncs');
    }
};