<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marque explicitement les tenants de démonstration et leur date d'expiration.
 *
 * - is_demo         : distingue un tenant démo (fictif, isolé, jetable) d'un vrai client.
 * - demo_expires_at : échéance au-delà de laquelle le tenant démo est révoqué/détruit
 *                     par la commande onboarding:revoke-expired-demos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->boolean('is_demo')->default(false)->after('status');
            $table->timestamp('demo_expires_at')->nullable()->after('is_demo');
            $table->index(['is_demo', 'demo_expires_at'], 'tenants_demo_idx');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropIndex('tenants_demo_idx');
            $table->dropColumn(['is_demo', 'demo_expires_at']);
        });
    }
};
