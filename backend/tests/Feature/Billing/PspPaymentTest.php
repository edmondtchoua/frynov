<?php

namespace Tests\Feature\Billing;

use App\Models\User;
use App\Modules\Billing\Models\ManualPayment;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Services\Psp\FakePspGateway;
use App\Modules\Tenants\Models\Tenant;
use Database\Seeders\PlansSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Option — paiement automatisé (PSP, driver `fake`) : initier → webhook signé → activation auto.
 */
class PspPaymentTest extends TestCase
{
    use RefreshDatabase;

    private function tenantUser(): array
    {
        $tenant = Tenant::create([
            'name' => 'B', 'slug' => 'b-'.substr(md5(uniqid('', true)), 0, 8),
            'plan' => Plan::CODE_STARTER, 'status' => 'active', 'subscription_status' => 'trialing',
        ]);
        Subscription::create([
            'tenant_id' => $tenant->id, 'plan_id' => Plan::where('code', Plan::CODE_STARTER)->value('id'),
            'status' => Subscription::STATUS_TRIALING, 'interval' => 'monthly',
            'currency' => 'XOF', 'market_code' => 'waemu', 'amount_paid_minor' => 0,
            'current_period_start' => now(), 'current_period_end' => now()->addDays(14),
        ]);

        return [$tenant, User::factory()->create(['tenant_id' => $tenant->id])];
    }

    #[Test]
    public function psp_is_disabled_by_default(): void
    {
        $this->seed(PlansSeeder::class);
        [, $user] = $this->tenantUser();
        Sanctum::actingAs($user);

        $this->postJson('/api/me/subscription/psp/initiate', [
            'plan_code' => Plan::CODE_ESSENTIAL, 'interval' => 'monthly', 'consent' => true,
        ])->assertStatus(503);
    }

    #[Test]
    public function initiate_then_signed_webhook_activates_the_plan(): void
    {
        config(['billing.psp.enabled' => true]);
        $this->seed(PlansSeeder::class);
        [$tenant, $user] = $this->tenantUser();

        // Un super-admin « système » approuve à la réception du webhook.
        $admin = User::factory()->create();
        $admin->forceFill(['is_super_admin' => true])->save();

        Sanctum::actingAs($user);
        $res = $this->postJson('/api/me/subscription/psp/initiate', [
            'plan_code' => Plan::CODE_ESSENTIAL, 'interval' => 'monthly', 'consent' => true, 'market_code' => 'waemu',
        ])->assertCreated();

        $reference = $res->json('reference');
        $this->assertNotEmpty($reference);
        $this->assertNotEmpty($res->json('checkout_url'));

        $payment = ManualPayment::withoutTenantScope()->where('psp_reference', $reference)->firstOrFail();
        $this->assertSame('psp', $payment->payment_method);
        $this->assertSame(ManualPayment::STATUS_PENDING, $payment->status);

        // Webhook non authentifié → rejeté.
        $this->postJson('/api/webhooks/psp', ['reference' => $reference, 'status' => 'success', 'signature' => 'bad'])
            ->assertStatus(400);

        // Webhook signé → activation automatique.
        $amount = (int) $payment->amount_cents;
        $this->postJson('/api/webhooks/psp', [
            'reference' => $reference, 'status' => 'success', 'amount_minor' => $amount,
            'signature' => FakePspGateway::sign($reference, 'success', $amount),
        ])->assertOk();

        $this->assertSame(ManualPayment::STATUS_APPROVED, $payment->fresh()->status);
        $this->assertDatabaseHas('subscriptions', [
            'tenant_id' => $tenant->id,
            'plan_id'   => Plan::where('code', Plan::CODE_ESSENTIAL)->value('id'),
            'status'    => Subscription::STATUS_ACTIVE,
        ]);
    }
}
