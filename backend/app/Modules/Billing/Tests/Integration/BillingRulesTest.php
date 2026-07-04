<?php

namespace App\Modules\Billing\Tests\Integration;

use App\Models\User;
use App\Modules\Billing\Models\ManualPayment;
use App\Modules\Billing\Models\MarketPaymentMethod;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Promotion;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Services\ManualPaymentService;
use App\Modules\Billing\Services\TenantCreditService;
use App\Modules\Tenants\Models\Tenant;
use Database\Seeders\PlansSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * RC-6G — les 6 règles billing configurables (arbitrage : « tout, mais configurable »).
 * Prix seedés (essential/waemu) : mensuel 990 000 XOF, annuel 9 900 000, siège +250 000/mois.
 */
class BillingRulesTest extends TestCase
{
    use RefreshDatabase;

    private ManualPaymentService $svc;
    private Tenant $tenant;
    private User $admin;
    private Plan $essential;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->seed(PlansSeeder::class);

        $this->svc       = app(ManualPaymentService::class);
        $this->essential = Plan::where('code', Plan::CODE_ESSENTIAL)->firstOrFail();
        $this->tenant    = Tenant::create(['name' => 'Rules', 'slug' => 'rules-test', 'plan' => 'starter', 'status' => 'active', 'settings' => []]);
        $this->admin     = User::create(['name' => 'SA', 'email' => 'sa@rules.sn', 'password' => bcrypt('x'), 'tenant_id' => $this->tenant->id, 'is_super_admin' => true]);
    }

    private function submit(int $amount, ?string $declared = null, ?string $promo = null, string $method = 'mobile_money', string $currency = 'XOF'): ManualPayment
    {
        return $this->svc->submit($this->tenant, $this->essential, $amount, $currency, $method, null, null, $promo, null, $declared);
    }

    private function sub(): ?Subscription
    {
        return Subscription::withoutTenantScope()->where('tenant_id', $this->tenant->id)
            ->where('plan_id', $this->essential->id)
            ->where('status', '!=', Subscription::STATUS_CANCELLED)
            ->latest()->first();
    }

    #[Test]
    public function a_valid_promo_resolves_to_the_net_target_and_activates(): void
    {
        Promotion::create([
            'code' => 'MOITIE', 'discount_type' => 'percent', 'discount_value' => 50,
            'valid_from' => now()->subDay(), 'valid_until' => now()->addMonth(), 'is_active' => true,
        ]);

        // 50 % de 990 000 = 495 000 → matched net + abonnement actif + usage promo enregistré.
        $mp = $this->submit(495000, 'monthly', 'MOITIE');
        $this->svc->approve($mp, $this->admin);

        $this->assertSame('matched', $mp->fresh()->resolution_status);
        $this->assertSame('active', $this->sub()->status);
        $this->assertDatabaseHas('promo_uses', ['tenant_id' => $this->tenant->id]);
    }

    #[Test]
    public function an_invalid_promo_still_routes_to_needs_review(): void
    {
        Promotion::create([
            'code' => 'FINI', 'discount_type' => 'percent', 'discount_value' => 50,
            'valid_from' => now()->subMonth(), 'valid_until' => now()->subDay(), 'is_active' => true, // expirée
        ]);

        $mp = $this->submit(495000, 'monthly', 'FINI');
        $this->svc->approve($mp, $this->admin);

        $this->assertSame('needs_review', $mp->fresh()->resolution_status);
        $this->assertNull($this->sub());
    }

    #[Test]
    public function extra_user_seats_are_detected_in_the_amount(): void
    {
        // base 990 000 + 2 sièges × 250 000 = 1 490 000 → matched avec 2 sièges.
        $mp = $this->submit(1490000, 'monthly');
        $this->svc->approve($mp, $this->admin);

        $this->assertSame('matched', $mp->fresh()->resolution_status);
        $sub = $this->sub();
        $this->assertSame('active', $sub->status);
        $this->assertSame(2, $sub->metadata['extra_users'] ?? null);
    }

    #[Test]
    public function a_currency_mismatching_the_payment_method_needs_review(): void
    {
        MarketPaymentMethod::create([
            'market_code' => 'waemu', 'currency' => 'XOF', 'method' => 'orange_money',
            'mode' => 'mobile_money', 'is_active' => true, 'display_order' => 1, 'label' => 'Orange Money',
        ]);

        // Paiement en EUR via un moyen déclaré XOF → approuvé SANS activation.
        $mp = $this->submit(700, 'monthly', null, 'orange_money', 'EUR');
        $this->svc->approve($mp, $this->admin);

        $this->assertSame('needs_review', $mp->fresh()->resolution_status);
        $this->assertSame(ManualPayment::STATUS_APPROVED, $mp->fresh()->status);
        $this->assertNull($this->sub());
    }

    #[Test]
    public function successive_deposits_top_up_the_same_past_due_subscription_in_place(): void
    {
        $d1 = $this->submit(300000, 'monthly');
        $this->svc->approve($d1, $this->admin);
        $d2 = $this->submit(300000, 'monthly');
        $this->svc->approve($d2, $this->admin);

        // RC-6G (règle 5) : UNE seule ligne d'abonnement (pas d'annulé par tranche), cumul abondé.
        $all = Subscription::withoutTenantScope()->where('tenant_id', $this->tenant->id)
            ->where('plan_id', $this->essential->id)->get();
        $this->assertCount(1, $all);
        $this->assertSame(Subscription::STATUS_PAST_DUE, $all->first()->status);
        $this->assertSame(600000, $all->first()->amount_paid_minor);
    }

    #[Test]
    public function rejecting_an_applied_deposit_reverses_its_cash_from_the_cycle(): void
    {
        $d1 = $this->submit(300000, 'monthly');
        $this->svc->approve($d1, $this->admin);
        $d2 = $this->submit(200000, 'monthly');
        $this->svc->approve($d2, $this->admin);
        $this->assertSame(500000, $this->sub()->amount_paid_minor);

        // RC-6G (règle 6) : rétro-action d'un acompte imputé NON soldé → cumul décrémenté.
        $this->svc->reject($d2->fresh(), $this->admin, 'Virement rejeté par la banque');

        $this->assertSame(ManualPayment::STATUS_REJECTED, $d2->fresh()->status);
        $this->assertSame(300000, $this->sub()->fresh()->amount_paid_minor);
    }

    #[Test]
    public function rejecting_a_payment_of_a_settled_cycle_stays_forbidden(): void
    {
        $mp = $this->submit(990000, 'monthly'); // solde → actif
        $this->svc->approve($mp, $this->admin);

        $this->expectException(\RuntimeException::class);
        $this->svc->reject($mp->fresh(), $this->admin, 'tentative');
    }

    #[Test]
    public function credit_consumption_is_capped_at_the_balance(): void
    {
        $credits = app(TenantCreditService::class);
        $credits->credit($this->tenant->id, 'XOF', 80000, 'overpaid', 'test');

        $this->assertSame(50000, $credits->consume($this->tenant->id, 'XOF', 50000, 'conso-1'));
        $this->assertSame(30000, $credits->consume($this->tenant->id, 'XOF', 90000, 'conso-2')); // borné
        $this->assertSame(0, $credits->balance($this->tenant->id, 'XOF'));
    }
}
