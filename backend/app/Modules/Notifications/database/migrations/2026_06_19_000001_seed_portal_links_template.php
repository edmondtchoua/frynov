<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * RC-6C — modèle global « mes achats » (portail client) : liste des liens magiques des achats
 * digitaux actifs d'un client, envoyée à sa demande depuis le portail public.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('notification_templates')->insert([
            'id' => (string) Str::uuid(), 'tenant_id' => null,
            'code' => 'digital.portal_links', 'channel' => 'email', 'locale' => 'fr',
            'subject' => 'Vos achats digitaux chez {{tenant_name}}',
            'body' => "Bonjour {{customer_name}},\n\nVoici vos {{count}} achat(s) digitaux actifs chez {{tenant_name}} :\n\n{{links}}\n\nChaque lien ouvre votre espace de téléchargement personnel. Ne le partagez pas.\n\n{{tenant_name}}",
            'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
        ]);

        // Le mail de livraison digitale porte désormais le LIEN MAGIQUE du portail (mode 2 de l'accès
        // client) en plus du jeton brut.
        DB::table('notification_templates')
            ->whereNull('tenant_id')
            ->where('code', 'digital.delivery')
            ->where('channel', 'email')
            ->update([
                'body' => "Bonjour {{customer_name}},\n\nMerci pour votre achat « {{product_name}} » (commande {{order_number}}).\n\nAccédez à vos fichiers et clés ici : {{portal_link}}\n\nVotre jeton d'accès : {{access_token}}\n{{license_line}}\n\nConservez ce message : il vous permet de télécharger vos achats.\n\n{{tenant_name}}",
                'updated_at' => $now,
            ]);
    }

    public function down(): void
    {
        DB::table('notification_templates')
            ->whereNull('tenant_id')
            ->where('code', 'digital.portal_links')
            ->delete();
    }
};
