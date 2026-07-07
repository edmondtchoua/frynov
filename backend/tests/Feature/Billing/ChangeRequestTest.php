<?php

namespace Tests\Feature\Billing;

use App\Models\User;
use App\Modules\Billing\Models\ManualPayment;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Models\SubscriptionChangeRequest;
use App\Modules\Billing\Services\ManualPaymentService;
use App\Modules\Tenants\Models\Tenant;
use Database\Seeders\PlansSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * P1 — la demande de changement de plan (`SubscriptionChangeRequest`) devient l'objet de premier plan :
 * créée à la soumission, rattachée au paiement, pilotée par une machine à états, avec snapshot figé.
 */
class ChangeRequestTest extends TestCase
{
    use RefreshDatabase;

    private function tenantWithUser(): array
    {
        $tenant = Tenant::create([
            'name'                => 'Boutique '.substr(md5(uniqid('', true)), 0, 6),
            'slug'                => 'b-'.substr(md5(uniqid('', true)), 0, 8),
            'plan'                => Plan::CODE_STARTER,
            'status'              => 'active',
            'subscription_status' => Subscription::STATUS_TRIALING,
        ]);

        Subscription::create([
            'tenant_id'            => $tenant->id,
            'plan_id'              => Plan::where('code', Plan::CODE_STARTER)->value('id'),
            'status'               => Subscription::STATUS_TRIALING,
            'interval'             => Subscription::INTERVAL_MONTHLY,
            'currency'             => 'XOF',
            'market_code'          => 'waemu',
            'amount_paid_minor'    => 0,
            'trial_ends_at'        => now()->addDays(14),
            'current_period_start' => now(),
            'current_period_end'   => now()->addDays(14),
        ]);

        return [$tenant, User::factory()->create(['tenant_id' => $tenant->id])];
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_super_admin' => true])->save();

        return $admin;
    }

    private function submitPayment(): string
    {
        $res = $this->postJson('/api/me/manual-payments', [
            'plan_code'      => Plan::CODE_ENTERPRISE,
            'interval'       => 'monthly',
            'payment_method' => 'orange_money',
            'market_code'    => 'waemu',
            'consent'        => true,
        ])->assertCreated();

        return $res->json('change_request_id');
    }

    #[Test]
    public function submitting_a_payment_creates_a_linked_change_request(): void
    {
        $this->seed(PlansSeeder::class);
        [$tenant, $user] = $this->tenantWithUser();
        Sanctum::actingAs($user);

        $crId = $this->submitPayment();

        $this->assertNotNull($crId);
        $cr = SubscriptionChangeRequest::findOrFail($crId);
        $this->assertSame(SubscriptionChangeRequest::STATUS_PENDING_VALIDATION, $cr->status);
        $this->assertSame(SubscriptionChangeRequest::TYPE_UPGRADE, $cr->change_type);
        $this->assertSame(5990000, $cr->net_payable_minor);
        $this->assertSame(Plan::CODE_ENTERPRISE, $cr->plan_snapshot['plan_code']);
        $this->assertSame(Plan::CODE_STARTER, $cr->plan_snapshot['from_plan_code']);

        // Le paiement pointe bien sur la demande.
        $payment = ManualPayment::where('tenant_id', $tenant->id)->firstOrFail();
        $this->assertSame($crId, $payment->change_request_id);
    }

    #[Test]
    public function approving_the_payment_activates_the_change_request(): void
    {
        $this->seed(PlansSeeder::class);
        [$tenant, $user] = $this->tenantWithUser();
        Sanctum::actingAs($user);
        $crId = $this->submitPayment();

        $payment = ManualPayment::withoutTenantScope()->where('change_request_id', $crId)->firstOrFail();
        app(ManualPaymentService::class)->approve($payment, $this->admin());

        $cr = SubscriptionChangeRequest::withoutTenantScope()->findOrFail($crId);
        $this->assertSame(SubscriptionChangeRequest::STATUS_ACTIVATED, $cr->status);
        $this->assertNotNull($cr->activated_at);

        // Le nouvel abonnement Enterprise est actif (l'ancien Découverte annulé).
        $this->assertDatabaseHas('subscriptions', [
            'tenant_id' => $tenant->id,
            'plan_id'   => Plan::where('code', Plan::CODE_ENTERPRISE)->value('id'),
            'status'    => Subscription::STATUS_ACTIVE,
        ]);
    }

    #[Test]
    public function rejecting_the_payment_rejects_the_change_request(): void
    {
        $this->seed(PlansSeeder::class);
        [, $user] = $this->tenantWithUser();
        Sanctum::actingAs($user);
        $crId = $this->submitPayment();

        $payment = ManualPayment::withoutTenantScope()->where('change_request_id', $crId)->firstOrFail();
        app(ManualPaymentService::class)->reject($payment, $this->admin(), 'Preuve illisible');

        $cr = SubscriptionChangeRequest::withoutTenantScope()->findOrFail($crId);
        $this->assertSame(SubscriptionChangeRequest::STATUS_REJECTED, $cr->status);
        $this->assertSame('Preuve illisible', $cr->rejection_reason);
    }

    #[Test]
    public function a_draft_can_be_created_submitted_then_cancelled(): void
    {
        $this->seed(PlansSeeder::class);
        [, $user] = $this->tenantWithUser();
        Sanctum::actingAs($user);

        $crId = $this->postJson('/api/me/subscription/change-requests', [
            'plan_code' => Plan::CODE_PRO,
            'interval'  => 'yearly',
        ])->assertCreated()->assertJsonPath('status', 'draft')->json('id');

        $this->postJson("/api/me/subscription/change-requests/{$crId}/submit")
            ->assertOk()->assertJsonPath('status', 'pending_validation');

        $this->postJson("/api/me/subscription/change-requests/{$crId}/cancel", ['reason' => 'Changement d\'avis'])
            ->assertOk()->assertJsonPath('status', 'cancelled');
    }

    #[Test]
    public function an_invalid_transition_is_refused(): void
    {
        $this->seed(PlansSeeder::class);
        [, $user] = $this->tenantWithUser();
        Sanctum::actingAs($user);

        $crId = $this->postJson('/api/me/subscription/change-requests', [
            'plan_code' => Plan::CODE_PRO,
            'interval'  => 'monthly',
        ])->assertCreated()->json('id');

        $this->postJson("/api/me/subscription/change-requests/{$crId}/cancel")->assertOk();
        // Une demande annulée (terminale) ne peut plus être soumise.
        $this->postJson("/api/me/subscription/change-requests/{$crId}/submit")->assertStatus(422);
    }

    #[Test]
    public function a_tenant_cannot_see_another_tenants_change_request(): void
    {
        $this->seed(PlansSeeder::class);
        [, $userA] = $this->tenantWithUser();
        Sanctum::actingAs($userA);
        $crId = $this->postJson('/api/me/subscription/change-requests', [
            'plan_code' => Plan::CODE_PRO,
            'interval'  => 'monthly',
        ])->assertCreated()->json('id');

        // Tenant B ne doit jamais atteindre la demande du tenant A (TenantScope fail-closed → 404).
        [, $userB] = $this->tenantWithUser();
        Sanctum::actingAs($userB);
        $this->getJson("/api/me/subscription/change-requests/{$crId}")->assertNotFound();
    }
}
