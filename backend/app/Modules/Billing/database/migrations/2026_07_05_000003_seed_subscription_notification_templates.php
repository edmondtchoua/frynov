<?php

use App\Modules\Notifications\Models\NotificationTemplate;
use Illuminate\Database\Migrations\Migration;

/**
 * P2 — modèles GLOBAUX (tenant_id null) des notifications de changement de plan, réutilisant l'outbox
 * existant. Idempotent (updateOrCreate) ; un tenant peut les surcharger via la table.
 */
return new class extends Migration
{
    private const TEMPLATES = [
        [
            'code'    => 'billing.subscription_submitted',
            'subject' => 'Votre demande de changement de plan a bien été reçue',
            'body'    => "Bonjour,\n\nVotre demande de passage au plan {{new_plan}} ({{interval}}, {{quantity}} période(s)) a bien été enregistrée pour {{tenant_name}}.\n\nMontant à régler : {{amount}}.\n\nElle sera activée après validation de votre paiement par un administrateur (sous 24h).\n\nL'équipe Frynov",
        ],
        [
            'code'    => 'billing.subscription_activated',
            'subject' => 'Votre nouveau plan {{new_plan}} est actif',
            'body'    => "Bonjour,\n\nBonne nouvelle : votre changement de plan vers {{new_plan}} est désormais actif pour {{tenant_name}}.\n\nPériodicité : {{interval}} — {{quantity}} période(s).\n\nMerci de votre confiance.\n\nL'équipe Frynov",
        ],
        [
            'code'    => 'billing.subscription_rejected',
            'subject' => 'Votre demande de changement de plan a été refusée',
            'body'    => "Bonjour,\n\nVotre demande de passage au plan {{new_plan}} pour {{tenant_name}} n'a pas pu être validée.\n\nMotif : {{reason}}\n\nVous pouvez soumettre une nouvelle demande depuis Paramètres → Abonnement.\n\nL'équipe Frynov",
        ],
        [
            'code'    => 'billing.subscription_admin_new',
            'subject' => '[Admin] Nouvelle demande de mise à niveau — {{tenant_name}}',
            'body'    => "Nouvelle demande de changement de plan à valider.\n\nTenant : {{tenant_name}}\nPlan demandé : {{new_plan}} ({{interval}}, {{quantity}} période(s))\nMontant déclaré : {{amount}}\n\nÀ traiter dans le back-office.",
        ],
    ];

    public function up(): void
    {
        foreach (self::TEMPLATES as $t) {
            NotificationTemplate::updateOrCreate(
                ['tenant_id' => null, 'code' => $t['code'], 'channel' => 'email', 'locale' => 'fr'],
                ['subject' => $t['subject'], 'body' => $t['body'], 'is_active' => true],
            );
        }
    }

    public function down(): void
    {
        NotificationTemplate::whereNull('tenant_id')
            ->whereIn('code', array_column(self::TEMPLATES, 'code'))
            ->where('channel', 'email')
            ->where('locale', 'fr')
            ->delete();
    }
};
