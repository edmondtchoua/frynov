<?php

namespace Tests\Feature\Billing;

use App\Models\User;
use App\Modules\Billing\Models\ManualPayment;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Promotion;
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
 * P0.1 — durée multi-période (mensuel 1–12, annuel 1–5, total = tarif unitaire × durée), tarif annuel
 * ×12 et promotion « en cours » appliquée automatiquement aux périodes COUVERTES par sa validité.
 */
class MultiPeriodQuoteTest extends TestCase
{
    use RefreshDatabase;

    private function tenantWithUser(): array
    {
        $tenant = Tenant::create([
            'name'                => 'B '.substr(md5(uniqid('', true)), 0, 6),
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

    #[Test]
    public function quantity_multiplies_the_total(): void
    {
        $this->seed(PlansSeeder::class);
        [, $user] = $this->tenantWithUser();
        Sanctum::actingAs($user);

        $this->postJson('/api/me/subscription/calculate-upgrade', [
            'plan_code' => Plan::CODE_ESSENTIAL,
            'interval'  => 'monthly',
            'quantity'  => 3,
        ])->assertOk()
            ->assertJsonPath('quantity', 3)
            ->assertJsonPath('unit_gross_minor', 990000)
            ->assertJsonPath('subtotal_minor', 2970000)
            ->assertJsonPath('net_payable_minor', 2970000);
    }

    #[Test]
    public function quantity_is_clamped_per_interval(): void
    {
        $this->seed(PlansSeeder::class);
        [, $user] = $this->tenantWithUser();
        Sanctum::actingAs($user);

        // Mensuel : borne haute 12 acceptée.
        $this->postJson('/api/me/subscription/calculate-upgrade', [
            'plan_code' => Plan::CODE_ESSENTIAL, 'interval' => 'monthly', 'quantity' => 12,
        ])->assertOk()->assertJsonPath('quantity', 12)->assertJsonPath('net_payable_minor', 990000 * 12);

        // Annuel > 5 → ramené à 5 côté serveur (tarif annuel = mensuel × 12).
        $this->postJson('/api/me/subscription/calculate-upgrade', [
            'plan_code' => Plan::CODE_ESSENTIAL, 'interval' => 'yearly', 'quantity' => 9,
        ])->assertOk()->assertJsonPath('quantity', 5)->assertJsonPath('net_payable_minor', 990000 * 12 * 5);

        // Le dépassement au-delà de la borne API (12) est refusé (422), pas silencieusement tronqué.
        $this->postJson('/api/me/subscription/calculate-upgrade', [
            'plan_code' => Plan::CODE_ESSENTIAL, 'interval' => 'monthly', 'quantity' => 20,
        ])->assertStatus(422);
    }

    #[Test]
    public function an_ongoing_promotion_is_applied_automatically_without_a_code(): void
    {
        $this->seed(PlansSeeder::class);
        [, $user] = $this->tenantWithUser();
        Promotion::create([
            'code'           => 'LANCEMENT10',
            'discount_type'  => 'percent',
            'discount_value' => 10,
            'is_active'      => true,
        ]);
        Sanctum::actingAs($user);

        $this->postJson('/api/me/subscription/calculate-upgrade', [
            'plan_code' => Plan::CODE_ESSENTIAL,
            'interval'  => 'monthly',
        ])->assertOk()
            ->assertJsonPath('promo.valid', true)
            ->assertJsonPath('promo.source', 'auto')
            ->assertJsonPath('promo.discount_minor', 99000)      // 10 % de 990 000
            ->assertJsonPath('net_payable_minor', 891000);
    }

    #[Test]
    public function a_promo_only_discounts_the_periods_it_covers(): void
    {
        $this->seed(PlansSeeder::class);
        [, $user] = $this->tenantWithUser();
        // Promo valable ~1 mois : sur 3 mois payés, seuls les 2 premiers débutent dans la fenêtre.
        Promotion::create([
            'code'           => 'PROMO10',
            'discount_type'  => 'percent',
            'discount_value' => 10,
            'valid_until'    => now()->addMonth()->addDays(10),
            'is_active'      => true,
        ]);
        Sanctum::actingAs($user);

        $this->postJson('/api/me/subscription/calculate-upgrade', [
            'plan_code'  => Plan::CODE_ESSENTIAL,
            'interval'   => 'monthly',
            'quantity'   => 3,
            'promo_code' => 'PROMO10',
        ])->assertOk()
            ->assertJsonPath('promo.covered_periods', 2)
            ->assertJsonPath('promo.discount_minor', 198000)      // 99 000 × 2 périodes couvertes
            ->assertJsonPath('net_payable_minor', 2970000 - 198000);
    }

    #[Test]
    public function a_multi_period_payment_grants_n_periods_on_approval(): void
    {
        $this->seed(PlansSeeder::class);
        [$tenant, $user] = $this->tenantWithUser();
        Sanctum::actingAs($user);

        $crId = $this->postJson('/api/me/manual-payments', [
            'plan_code'      => Plan::CODE_ESSENTIAL,
            'interval'       => 'monthly',
            'quantity'       => 3,
            'payment_method' => 'orange_money',
            'market_code'    => 'waemu',
            'consent'        => true,
        ])->assertCreated()->json('change_request_id');

        // Le paiement porte le net autoritatif de 3 mois.
        $payment = ManualPayment::withoutTenantScope()->where('change_request_id', $crId)->firstOrFail();
        $this->assertSame(2970000, $payment->amount_cents);

        app(ManualPaymentService::class)->approve($payment, $this->admin());

        $cr = SubscriptionChangeRequest::withoutTenantScope()->findOrFail($crId);
        $this->assertSame(SubscriptionChangeRequest::STATUS_ACTIVATED, $cr->status);
        $this->assertSame(3, $cr->quantity);

        // Abonnement actif dont la période court sur ~3 mois.
        $sub = Subscription::withoutTenantScope()
            ->where('tenant_id', $tenant->id)
            ->where('status', Subscription::STATUS_ACTIVE)
            ->firstOrFail();
        $this->assertTrue($sub->current_period_end->greaterThan(now()->addMonths(2)->addDays(25)));
        $this->assertTrue($sub->current_period_end->lessThan(now()->addMonths(3)->addDays(5)));
    }
}
