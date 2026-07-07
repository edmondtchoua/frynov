<?php

namespace Tests\Feature\Billing;

use App\Models\User;
use App\Modules\Billing\Models\ManualPayment;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Promotion;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Tenants\Models\Tenant;
use Database\Seeders\PlansSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * P0 — « montant autoritatif ». Prouve que le devis de changement de plan est calculé serveur-side et
 * qu'un montant client ne peut plus falsifier ni le total ni la cible du plan (critère d'acceptation #22).
 */
class CalculateUpgradeTest extends TestCase
{
    use RefreshDatabase;

    private function tenantWithUser(string $market = 'waemu', string $currency = 'XOF'): array
    {
        $tenant = Tenant::create([
            'name'                => 'Boutique Test',
            'slug'                => 'boutique-'.substr(md5(uniqid('', true)), 0, 8),
            'plan'                => Plan::CODE_STARTER,
            'status'              => 'active',
            'subscription_status' => Subscription::STATUS_TRIALING,
        ]);

        // Abonnement courant d'essai, sans temps payé → aucun avoir de proration (net = brut après promo).
        Subscription::create([
            'tenant_id'            => $tenant->id,
            'plan_id'              => Plan::where('code', Plan::CODE_STARTER)->value('id'),
            'status'               => Subscription::STATUS_TRIALING,
            'interval'             => Subscription::INTERVAL_MONTHLY,
            'currency'             => $currency,
            'market_code'          => $market,
            'amount_paid_minor'    => 0,
            'trial_ends_at'        => now()->addDays(14),
            'current_period_start' => now(),
            'current_period_end'   => now()->addDays(14),
        ]);

        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        return [$tenant, $user];
    }

    #[Test]
    public function it_returns_the_authoritative_monthly_net_for_a_market(): void
    {
        $this->seed(PlansSeeder::class);
        [, $user] = $this->tenantWithUser();
        Sanctum::actingAs($user);

        $res = $this->postJson('/api/me/subscription/calculate-upgrade', [
            'plan_code' => Plan::CODE_ENTERPRISE,
            'interval'  => 'monthly',
        ]);

        $res->assertOk()
            ->assertJson([
                'plan_code'         => Plan::CODE_ENTERPRISE,
                'interval'          => 'monthly',
                'market'            => 'waemu',
                'currency'          => 'XOF',
                'exponent'          => 0,
                'base_gross_minor'  => 5990000,
                'net_payable_minor' => 5990000,
            ]);
    }

    #[Test]
    public function yearly_interval_recomputes_the_net(): void
    {
        $this->seed(PlansSeeder::class);
        [, $user] = $this->tenantWithUser();
        Sanctum::actingAs($user);

        // Annuel = 12× le mensuel (5 990 000 × 12).
        $this->postJson('/api/me/subscription/calculate-upgrade', [
            'plan_code' => Plan::CODE_ENTERPRISE,
            'interval'  => 'yearly',
        ])->assertOk()->assertJsonPath('net_payable_minor', 71880000);
    }

    #[Test]
    public function a_valid_percent_promo_reduces_the_net(): void
    {
        $this->seed(PlansSeeder::class);
        [, $user] = $this->tenantWithUser();
        Promotion::create([
            'code'           => 'PROMO20',
            'discount_type'  => 'percent',
            'discount_value' => 20,
            'is_active'      => true,
        ]);
        Sanctum::actingAs($user);

        $this->postJson('/api/me/subscription/calculate-upgrade', [
            'plan_code'  => Plan::CODE_ENTERPRISE,
            'interval'   => 'monthly',
            'promo_code' => 'promo20',
        ])->assertOk()
            ->assertJsonPath('promo.valid', true)
            ->assertJsonPath('promo.discount_minor', 1198000)   // 20 % de 5 990 000
            ->assertJsonPath('net_payable_minor', 4792000);
    }

    #[Test]
    public function an_invalid_promo_is_flagged_and_does_not_change_the_net(): void
    {
        $this->seed(PlansSeeder::class);
        [, $user] = $this->tenantWithUser();
        Sanctum::actingAs($user);

        $this->postJson('/api/me/subscription/calculate-upgrade', [
            'plan_code'  => Plan::CODE_ENTERPRISE,
            'interval'   => 'monthly',
            'promo_code' => 'DOESNOTEXIST',
        ])->assertOk()
            ->assertJsonPath('promo.valid', false)
            ->assertJsonPath('net_payable_minor', 5990000);
    }

    #[Test]
    public function submit_without_amount_uses_the_authoritative_net(): void
    {
        $this->seed(PlansSeeder::class);
        [$tenant, $user] = $this->tenantWithUser();
        Sanctum::actingAs($user);

        // Parcours verrouillé : le front n'envoie PAS de montant → le backend impose le net du devis.
        $this->postJson('/api/me/manual-payments', [
            'plan_code'      => Plan::CODE_ENTERPRISE,
            'interval'       => 'monthly',
            'payment_method' => 'orange_money',
            'market_code'    => 'waemu',
            'consent'        => true,
        ])->assertCreated();

        $this->assertDatabaseHas('manual_payments', [
            'tenant_id'    => $tenant->id,
            'amount_cents' => 5990000,
            'currency'     => 'XOF',
        ]);
    }

    #[Test]
    public function a_forged_client_amount_cannot_falsify_the_plan_target(): void
    {
        $this->seed(PlansSeeder::class);
        [$tenant, $user] = $this->tenantWithUser();
        Sanctum::actingAs($user);

        // Un tenant malicieux déclare 1 XOF pour le plan Enterprise. Le versement est stocké tel quel
        // (acompte), mais la CIBLE reste dérivée serveur-side : impossible de « payer » 1 XOF le plan.
        $this->postJson('/api/me/manual-payments', [
            'plan_code'      => Plan::CODE_ENTERPRISE,
            'interval'       => 'monthly',
            'amount_cents'   => 1,
            'payment_method' => 'orange_money',
            'market_code'    => 'waemu',
            'consent'        => true,
        ])->assertCreated();

        $payment = ManualPayment::where('tenant_id', $tenant->id)->firstOrFail();
        $this->assertSame(1, $payment->amount_cents);
        $this->assertSame(5990000, (int) $payment->target_amount_minor, 'La cible du plan doit rester autoritative.');
        $this->assertNotSame(ManualPayment::STATUS_APPROVED, $payment->status, 'Un acompte dérisoire ne doit jamais activer le plan.');
    }

    #[Test]
    public function it_requires_authentication(): void
    {
        $this->seed(PlansSeeder::class);
        $this->postJson('/api/me/subscription/calculate-upgrade', [
            'plan_code' => Plan::CODE_ENTERPRISE,
            'interval'  => 'monthly',
        ])->assertUnauthorized();
    }
}
