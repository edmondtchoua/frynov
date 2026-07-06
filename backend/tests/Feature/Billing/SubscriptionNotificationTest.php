<?php

namespace Tests\Feature\Billing;

use App\Models\User;
use App\Modules\Billing\Models\ManualPayment;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Services\ManualPaymentService;
use App\Modules\Notifications\Models\NotificationChannel;
use App\Modules\Tenants\Models\Tenant;
use Database\Seeders\PlansSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * P2 — le cycle de vie d'une demande émet des notifications (outbox réutilisé), historisées.
 */
class SubscriptionNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function setup_tenant(): array
    {
        $tenant = Tenant::create([
            'name' => 'Boutique Notif', 'slug' => 'bn-'.substr(md5(uniqid('', true)), 0, 8),
            'plan' => Plan::CODE_STARTER, 'status' => 'active',
            'subscription_status' => Subscription::STATUS_TRIALING,
        ]);
        Subscription::create([
            'tenant_id' => $tenant->id, 'plan_id' => Plan::where('code', Plan::CODE_STARTER)->value('id'),
            'status' => Subscription::STATUS_TRIALING, 'interval' => Subscription::INTERVAL_MONTHLY,
            'currency' => 'XOF', 'market_code' => 'waemu', 'amount_paid_minor' => 0,
            'trial_ends_at' => now()->addDays(14), 'current_period_start' => now(), 'current_period_end' => now()->addDays(14),
        ]);
        // Canal email actif (provider log) → l'outbox reçoit les notifications.
        NotificationChannel::create([
            'tenant_id' => $tenant->id, 'channel' => 'email', 'provider' => 'log',
            'name' => 'Log', 'from_name' => 'Frynov', 'from_address' => 'no-reply@frynov.test',
            'is_active' => true, 'is_default' => true,
        ]);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        return [$tenant, $user];
    }

    private function admin(): User
    {
        $a = User::factory()->create(['email' => 'sa-'.substr(md5(uniqid('', true)), 0, 6).'@frynov.test']);
        $a->forceFill(['is_super_admin' => true])->save();

        return $a;
    }

    private function submit(): string
    {
        return $this->postJson('/api/me/manual-payments', [
            'plan_code' => Plan::CODE_ENTERPRISE, 'interval' => 'monthly',
            'payment_method' => 'orange_money', 'market_code' => 'waemu', 'consent' => true,
        ])->assertCreated()->json('change_request_id');
    }

    #[Test]
    public function submitting_notifies_the_tenant_and_internal_admins(): void
    {
        $this->seed(PlansSeeder::class);
        [, $user] = $this->setup_tenant();
        $this->admin();                 // un super-admin destinataire de l'alerte
        Sanctum::actingAs($user);

        $this->submit();

        $this->assertDatabaseHas('notification_outbox', ['template_code' => 'billing.subscription_submitted']);
        $this->assertDatabaseHas('notification_outbox', ['template_code' => 'billing.subscription_admin_new']);
    }

    #[Test]
    public function approval_notifies_the_tenant_of_activation(): void
    {
        $this->seed(PlansSeeder::class);
        [, $user] = $this->setup_tenant();
        Sanctum::actingAs($user);
        $crId = $this->submit();

        $payment = ManualPayment::withoutTenantScope()->where('change_request_id', $crId)->firstOrFail();
        app(ManualPaymentService::class)->approve($payment, $this->admin());

        $this->assertDatabaseHas('notification_outbox', ['template_code' => 'billing.subscription_activated']);
    }

    #[Test]
    public function rejection_notifies_the_tenant(): void
    {
        $this->seed(PlansSeeder::class);
        [, $user] = $this->setup_tenant();
        Sanctum::actingAs($user);
        $crId = $this->submit();

        $payment = ManualPayment::withoutTenantScope()->where('change_request_id', $crId)->firstOrFail();
        app(ManualPaymentService::class)->reject($payment, $this->admin(), 'Preuve illisible');

        $this->assertDatabaseHas('notification_outbox', [
            'template_code' => 'billing.subscription_rejected',
        ]);
    }
}
