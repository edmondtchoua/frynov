<?php

namespace App\Modules\Billing\Tests\Integration;

use App\Models\User;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Tenants\Models\Tenant;
use Database\Seeders\PlansSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * RC-2A — endpoint preview de proration (lecture seule). Vérifie le reliquat affiché et l'absence
 * de mutation.
 */
class PreviewUpgradeTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private string $token;
    private Plan $essential;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlansSeeder::class);
        $this->essential = Plan::where('code', Plan::CODE_ESSENTIAL)->firstOrFail();

        $this->tenant = Tenant::create(['name' => 'Pv', 'slug' => 'pv-test', 'plan' => 'essential', 'status' => 'active', 'settings' => []]);
        $this->user = User::create(['name' => 'U', 'email' => 'u@pv.sn', 'password' => Hash::make('x'), 'tenant_id' => $this->tenant->id]);
        $this->token = $this->user->createToken('api')->plainTextToken;
    }

    private function activeMonthly(): Subscription
    {
        return Subscription::create([
            'tenant_id'            => $this->tenant->id,
            'plan_id'              => $this->essential->id,
            'status'               => Subscription::STATUS_ACTIVE,
            'interval'             => Subscription::INTERVAL_MONTHLY,
            'currency'             => 'XOF',
            'market_code'          => 'waemu',
            'amount_paid_minor'    => 990000,
            'current_period_start' => now(),
            'current_period_end'   => now()->addMonth(),
        ]);
    }

    private function preview(array $body): \Illuminate\Testing\TestResponse
    {
        return $this->withToken($this->token)->postJson('/api/me/subscription/preview-upgrade', $body);
    }

    #[Test]
    public function it_previews_the_proration_credit_for_a_mid_cycle_upgrade(): void
    {
        $this->activeMonthly();

        $res = $this->preview(['plan_code' => Plan::CODE_ESSENTIAL, 'interval' => 'yearly'])->assertOk();

        $res->assertJsonPath('eligible', true)
            ->assertJsonPath('currency', 'XOF')
            ->assertJsonPath('exponent', 0)
            ->assertJsonPath('new_gross_minor', 9900000);

        // Upgrade quasi immédiat → crédit ≈ tout le mensuel payé ; net = annuel − crédit.
        $credit  = $res->json('credit_minor');
        $applied = $res->json('applied_credit_minor');
        $net     = $res->json('net_payable_minor');
        $this->assertGreaterThan(980000, $credit);
        $this->assertLessThanOrEqual(990000, $credit);
        $this->assertSame($credit, $applied);
        $this->assertSame(9900000 - $applied, $net);

        // Lecture seule : l'abonnement courant n'est pas muté.
        $this->assertSame('active', Subscription::withoutTenantScope()->where('tenant_id', $this->tenant->id)->latest()->first()->status);
    }

    #[Test]
    public function without_a_current_subscription_no_credit(): void
    {
        $res = $this->preview(['plan_code' => Plan::CODE_ESSENTIAL, 'interval' => 'monthly'])->assertOk();

        $res->assertJsonPath('eligible', false)
            ->assertJsonPath('reason', 'not_paid')
            ->assertJsonPath('credit_minor', 0);
    }

    #[Test]
    public function it_rejects_an_unsupported_interval(): void
    {
        $this->activeMonthly();
        $this->preview(['plan_code' => Plan::CODE_ESSENTIAL, 'interval' => 'weekly'])->assertStatus(422);
    }
}
