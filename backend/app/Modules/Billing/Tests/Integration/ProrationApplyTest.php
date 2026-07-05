<?php

namespace App\Modules\Billing\Tests\Integration;

use App\Models\User;
use App\Modules\Billing\Models\ManualPayment;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Services\ManualPaymentService;
use App\Modules\Tenants\Models\Tenant;
use Database\Seeders\PlansSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * RC-2B — application du reliquat à l'upgrade (paiement manuel, modèle « acompte virtuel ») : le client
 * vire le NET, le crédit du temps non consommé comble le reste → abonnement activé. amount_paid_minor
 * reste le cash réel.
 */
class ProrationApplyTest extends TestCase
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
        $this->tenant    = Tenant::create(['name' => 'Pr', 'slug' => 'pr-test', 'plan' => 'essential', 'status' => 'active', 'settings' => []]);
        $this->admin     = User::create(['name' => 'SA', 'email' => 'sa@pr.sn', 'password' => Hash::make('x'), 'tenant_id' => $this->tenant->id, 'is_super_admin' => true]);
    }

    private function activeMonthly(int $paid = 990000): Subscription
    {
        return Subscription::create([
            'tenant_id'            => $this->tenant->id,
            'plan_id'              => $this->essential->id,
            'status'               => Subscription::STATUS_ACTIVE,
            'interval'             => Subscription::INTERVAL_MONTHLY,
            'currency'             => 'XOF',
            'market_code'          => 'waemu',
            'amount_paid_minor'    => $paid,
            'current_period_start' => now(),
            'current_period_end'   => now()->addMonth(),
        ]);
    }

    private function sub(): ?Subscription
    {
        return Subscription::withoutTenantScope()->where('tenant_id', $this->tenant->id)
            ->where('status', '!=', Subscription::STATUS_CANCELLED)->latest()->first();
    }

    private function submit(int $amount, string $declared): ManualPayment
    {
        return $this->svc->submit($this->tenant, $this->essential, $amount, 'XOF', 'mobile_money', null, null, null, null, $declared);
    }

    #[Test]
    public function upgrade_monthly_to_yearly_lets_the_client_pay_only_the_net(): void
    {
        $this->activeMonthly(990000);

        // Annuel 9 900 000 − reliquat (~990 000 le jour 1) = NET ~8 910 000.
        $net = 9900000 - 990000;
        $mp  = $this->submit($net, 'yearly');
        $this->svc->approve($mp, $this->admin);

        $sub = $this->sub();
        $this->assertSame('active', $sub->status);
        $this->assertSame('yearly', $sub->interval);
        // Cash réel encaissé = net (le crédit n'enfle PAS amount_paid_minor).
        $this->assertSame($net, $sub->amount_paid_minor);
        // Crédit appliqué tracé (~990 000), audit proration présent.
        $this->assertGreaterThan(980000, $sub->metadata['credit_applied_minor'] ?? 0);
        $this->assertArrayHasKey('proration', $sub->metadata ?? []);
        $this->assertSame('matched', $mp->fresh()->resolution_status);
    }

    #[Test]
    public function a_renewal_same_plan_same_interval_gets_no_proration(): void
    {
        $this->activeMonthly(990000);

        $mp = $this->submit(990000, 'monthly'); // même plan + même périodicité = renouvellement
        $this->svc->approve($mp, $this->admin);

        $sub = $this->sub();
        $this->assertSame('active', $sub->status);
        $this->assertSame('monthly', $sub->interval);
        $this->assertSame(990000, $sub->amount_paid_minor);
        $this->assertArrayNotHasKey('credit_applied_minor', $sub->metadata ?? []);
    }

    #[Test]
    public function first_purchase_without_current_subscription_has_no_proration(): void
    {
        $mp = $this->submit(990000, 'monthly'); // aucun abonnement courant
        $this->svc->approve($mp, $this->admin);

        $sub = $this->sub();
        $this->assertSame('active', $sub->status);
        $this->assertSame(990000, $sub->amount_paid_minor);
        $this->assertArrayNotHasKey('credit_applied_minor', $sub->metadata ?? []);
    }

    #[Test]
    public function approving_the_upgrade_twice_is_idempotent(): void
    {
        $this->activeMonthly(990000);
        $net = 9900000 - 990000;
        $mp  = $this->submit($net, 'yearly');

        $this->svc->approve($mp, $this->admin);
        $this->svc->approve($mp->fresh(), $this->admin); // déjà approuvé → no-op

        $active = Subscription::withoutTenantScope()->where('tenant_id', $this->tenant->id)
            ->where('status', 'active')->get();
        $this->assertCount(1, $active);
        $this->assertSame($net, $active->first()->amount_paid_minor); // pas doublé
    }
}
