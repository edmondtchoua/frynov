<?php

use App\Modules\Notifications\Models\NotificationTemplate;
use Illuminate\Database\Migrations\Migration;

/** Option — modèle e-mail de la demande de correction (admin → tenant). Idempotent. */
return new class extends Migration
{
    public function up(): void
    {
        NotificationTemplate::updateOrCreate(
            ['tenant_id' => null, 'code' => 'billing.subscription_correction', 'channel' => 'email', 'locale' => 'fr'],
            [
                'subject' => 'Correction demandée sur votre demande de changement de plan',
                'body'    => "Bonjour,\n\nVotre demande de passage au plan {{new_plan}} pour {{tenant_name}} nécessite une correction avant validation.\n\nConsigne : {{reason}}\n\nMerci de mettre à jour votre demande depuis Paramètres → Abonnement.\n\nL'équipe Frynov",
                'is_active' => true,
            ],
        );
    }

    public function down(): void
    {
        NotificationTemplate::whereNull('tenant_id')->where('code', 'billing.subscription_correction')->delete();
    }
};
