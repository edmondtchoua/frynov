<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * RC-6A — MODÈLES de notification dynamiques. `tenant_id` NULL = modèle GLOBAL par défaut (seedé
 * ci-dessous, FR source de vérité) ; un tenant peut le surcharger (résolution : tenant → global).
 * Placeholders `{{variable}}` rendus au moment de l'envoi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->nullable()->index(); // null = global (défaut plateforme)

            $table->string('code', 64);              // ex. billing.renewal_reminder
            $table->string('channel', 16);           // email | sms | whatsapp
            $table->string('locale', 5)->default('fr');
            $table->string('subject', 190)->nullable(); // email uniquement
            $table->text('body');                       // placeholders {{var}}
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'code', 'channel', 'locale'], 'notif_templates_scope_unique');
            $table->index(['code', 'channel'], 'notif_templates_code_channel_idx');
        });

        // ── Modèles globaux par défaut (FR — source de vérité) ─────────────
        $now  = now();
        $rows = [
            [
                'code' => 'billing.renewal_reminder', 'channel' => 'email',
                'subject' => 'Votre abonnement {{plan}} arrive à échéance le {{period_end}}',
                'body' => "Bonjour {{tenant_name}},\n\nVotre abonnement {{plan}} arrive à échéance le {{period_end}} (dans {{days_left}} jour(s)).\nPour éviter toute interruption, merci de renouveler votre paiement.\n\nL'équipe Frynov",
            ],
            [
                'code' => 'billing.renewal_overdue', 'channel' => 'email',
                'subject' => 'Abonnement {{plan}} échu — paiement attendu',
                'body' => "Bonjour {{tenant_name}},\n\nVotre abonnement {{plan}} est arrivé à échéance le {{period_end}}.\nVous disposez d'un délai de grâce de {{grace_days}} jours avant suspension.\n\nL'équipe Frynov",
            ],
            [
                'code' => 'billing.renewal_suspended', 'channel' => 'email',
                'subject' => 'Abonnement {{plan}} suspendu',
                'body' => "Bonjour {{tenant_name}},\n\nFaute de paiement, votre abonnement {{plan}} a été suspendu.\nContactez-nous après régularisation pour réactiver votre accès.\n\nL'équipe Frynov",
            ],
            [
                'code' => 'digital.delivery', 'channel' => 'email',
                'subject' => 'Votre achat {{product_name}} — accès de téléchargement',
                'body' => "Bonjour {{customer_name}},\n\nMerci pour votre achat « {{product_name}} » (commande {{order_number}}).\nVotre jeton d'accès : {{access_token}}\n{{license_line}}\n\nConservez ce message : il vous permet de télécharger vos fichiers.\n\n{{tenant_name}}",
            ],
        ];

        foreach ($rows as $r) {
            DB::table('notification_templates')->insert(array_merge($r, [
                'id' => (string) Str::uuid(), 'tenant_id' => null, 'locale' => 'fr',
                'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
            ]));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_templates');
    }
};
