<?php

namespace Tests\Feature\Billing;

use App\Models\User;
use App\Modules\Billing\Models\ManualPayment;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Models\SubscriptionChangeRequest;
use App\Modules\Billing\Services\ManualPaymentService;
use App\Modules\Billing\Services\RenewalService;
use App\Modules\Tenants\Models\Tenant;
use Database\Seeders\PlansSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * P4 — upgrade DIFFÉRÉ (prochain cycle) : le paiement est encaissé et approuvé, mais le changement de
 * plan n'est appliqué qu'à l'échéance du cycle courant (par le cron de renouvellement).
 */
class DeferredUpgradeTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_next_cycle_change_is_scheduled_then_applied_at_renewal(): void
    {
        $this->seed(PlansSeeder::class);

        // Tenant sur un plan PAYANT actif, échéance dans 10 jours (rien de prorata : amount_paid 0).
        $tenant = Tenant::create([
            'name' => 'B', 'slug' => 'b-'.substr(md5(uniqid('', true)), 0, 8),
            'plan' => Plan::CODE_PRO, 'status' => 'active', 'subscription_status' => 'active',
        ]);
        $proSub = Subscription::create([
            'tenant_id' => $tenant->id, 'plan_id' => Plan::where('code', Plan::CODE_PRO)->value('id'),
            'status' => Subscription::STATUS_ACTIVE, 'interval' => Subscription::INTERVAL_MONTHLY,
            'currency' => 'XOF', 'market_code' => 'waemu', 'amount_paid_minor' => 0,
            'current_period_start' => now()->subDays(20), 'current_period_end' => now()->addDays(10),
        ]);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $admin = User::factory()->create();
        $admin->forceFill(['is_super_admin' => true])->save();
        Sanctum::actingAs($user);

        // Demande d'upgrade Enterprise, prise d'effet AU PROCHAIN CYCLE.
        $crId = $this->postJson('/api/me/manual-payments', [
            'plan_code' => Plan::CODE_ENTERPRISE, 'interval' => 'monthly',
            'payment_method' => 'orange_money', 'market_code' => 'waemu',
            'consent' => true, 'effective' => 'next_cycle',
        ])->assertCreated()->json('change_request_id');

        // Approbation → PLANIFIÉ, pas activé.
        $payment = ManualPayment::withoutTenantScope()->where('change_request_id', $crId)->firstOrFail();
        app(ManualPaymentService::class)->approve($payment, $admin);

        $cr = SubscriptionChangeRequest::withoutTenantScope()->findOrFail($crId);
        $this->assertSame(SubscriptionChangeRequest::STATUS_APPROVED, $cr->status, 'La demande différée doit être approuvée, pas activée.');
        $this->assertNull($cr->activated_at);

        // Le tenant est TOUJOURS sur Croissance ; le changement est planifié sur l'abonnement courant.
        $proSub->refresh();
        $this->assertSame(Subscription::STATUS_ACTIVE, $proSub->status);
        $this->assertSame(Plan::where('code', Plan::CODE_ENTERPRISE)->value('id'), $proSub->metadata['scheduled_change']['plan_id']);

        // ── Échéance atteinte → le cron applique le changement planifié ──────────────────────────
        $proSub->update(['current_period_end' => now()->subDay()]);
        app(RenewalService::class)->processRenewals();

        $cr->refresh();
        $this->assertSame(SubscriptionChangeRequest::STATUS_ACTIVATED, $cr->status);

        // Nouvel abonnement Enterprise actif.
        $this->assertDatabaseHas('subscriptions', [
            'tenant_id' => $tenant->id,
            'plan_id'   => Plan::where('code', Plan::CODE_ENTERPRISE)->value('id'),
            'status'    => Subscription::STATUS_ACTIVE,
        ]);
    }
}
