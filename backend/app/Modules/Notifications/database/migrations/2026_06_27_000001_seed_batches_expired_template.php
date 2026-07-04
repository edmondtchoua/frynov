<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * RC-7A — modèle global d'alerte de démarque : lots passés `expired` à retirer physiquement du stock.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('notification_templates')->insert([
            'id' => (string) Str::uuid(), 'tenant_id' => null,
            'code' => 'inventory.batches_expired', 'channel' => 'email', 'locale' => 'fr',
            'subject' => '{{count}} lot(s) périmé(s) à retirer du stock',
            'body' => "Bonjour {{tenant_name}},\n\nLes lots suivants ont dépassé leur date de péremption et ont été retirés de la vente :\n\n{{lines}}\n\nMerci de les retirer physiquement du stock (démarque).\n\nFrynov",
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('notification_templates')->whereNull('tenant_id')->where('code', 'inventory.batches_expired')->delete();
    }
};
