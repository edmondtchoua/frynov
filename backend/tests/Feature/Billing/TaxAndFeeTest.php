<?php

namespace Tests\Feature\Billing;

use App\Models\User;
use App\Modules\Billing\Models\ManualPayment;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Services\ManualPaymentService;
use App\Modules\Tenants\Models\Tenant;
use Database\Seeders\PlansSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Option — taxe (points de base) + frais d'installation (unique) intégrés au devis et au règlement.
 */
class TaxAndFeeTest extends TestCase
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
    public function tax_and_setup_fee_are_added_to_the_quote_and_settled(): void
    {
        $this->seed(PlansSeeder::class);
        // Essentiel : TVA 18 % + frais d'installation 1 000 (×100 = 100000).
        Plan::where('code', Plan::CODE_ESSENTIAL)->update(['tax_rate_bps' => 1800, 'setup_fee_minor' => 100000]);

        [$tenant, $user] = $this->tenantUser();
        Sanctum::actingAs($user);

        // Devis : base 990000 + taxe 178200 (18 %) + frais 100000 = 1 268 200.
        $this->postJson('/api/me/subscription/calculate-upgrade', [
            'plan_code' => Plan::CODE_ESSENTIAL, 'interval' => 'monthly',
        ])->assertOk()
            ->assertJsonPath('tax_minor', 178200)
            ->assertJsonPath('setup_fee_minor', 100000)
            ->assertJsonPath('net_payable_minor', 1268200);

        // Soumission → approbation : la taxe/les frais ne sont PAS pris pour un trop-perçu ; le plan s'active.
        $crId = $this->postJson('/api/me/manual-payments', [
            'plan_code' => Plan::CODE_ESSENTIAL, 'interval' => 'monthly',
            'payment_method' => 'orange_money', 'market_code' => 'waemu', 'consent' => true,
        ])->assertCreated()->json('change_request_id');

        $payment = ManualPayment::withoutTenantScope()->where('change_request_id', $crId)->firstOrFail();
        $this->assertSame(1268200, $payment->amount_cents);

        $admin = User::factory()->create();
        $admin->forceFill(['is_super_admin' => true])->save();
        app(ManualPaymentService::class)->approve($payment, $admin);

        $this->assertDatabaseHas('subscriptions', [
            'tenant_id' => $tenant->id,
            'plan_id'   => Plan::where('code', Plan::CODE_ESSENTIAL)->value('id'),
            'status'    => Subscription::STATUS_ACTIVE,
        ]);
    }

    #[Test]
    public function no_setup_fee_when_staying_on_the_same_plan(): void
    {
        $this->seed(PlansSeeder::class);
        Plan::where('code', Plan::CODE_STARTER)->update(['setup_fee_minor' => 100000]);

        [, $user] = $this->tenantUser(); // déjà sur starter
        Sanctum::actingAs($user);

        // Même plan (starter → starter) : pas de frais d'installation.
        $this->postJson('/api/me/subscription/calculate-upgrade', [
            'plan_code' => Plan::CODE_STARTER, 'interval' => 'monthly',
        ])->assertOk()->assertJsonPath('setup_fee_minor', 0);
    }
}
