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
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * RC-1C — détection de périodicité & acompte échelonné de bout en bout (submit → approve), avec un
 * plan TARIFÉ (Essentiel : XOF mensuel 990000 / annuel 9 900 000). Couvre les correctifs de la revue
 * adverse : trop-perçu seulement au-delà de la cible, renouvellement non compté comme avoir, acompte
 * past_due puis solde, idempotence, promo→needs_review, devise inconnue→unmatched.
 */
class ManualPaymentDetectionTest extends TestCase
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
        $this->tenant    = Tenant::create(['name' => 'Det', 'slug' => 'det-test', 'plan' => 'starter', 'status' => 'active', 'settings' => []]);
        $this->admin     = User::create(['name' => 'SA', 'email' => 'sa@det.sn', 'password' => bcrypt('x'), 'tenant_id' => $this->tenant->id, 'is_super_admin' => true]);
    }

    private function submit(int $amount, ?string $declared = null, ?string $promo = null): ManualPayment
    {
        return $this->svc->submit($this->tenant, $this->essential, $amount, 'XOF', 'mobile_money', null, null, $promo, null, $declared);
    }

    private function sub(): ?Subscription
    {
        // L'abonnement COURANT = le seul non annulé (changePlan annule le précédent). On exclut donc
        // 'cancelled' plutôt que de se fier à latest() (created_at ambigu à la seconde près en test).
        return Subscription::withoutTenantScope()->where('tenant_id', $this->tenant->id)
            ->where('plan_id', $this->essential->id)
            ->where('status', '!=', Subscription::STATUS_CANCELLED)
            ->latest()->first();
    }

    #[Test]
    public function submit_persists_detection_without_creating_a_subscription(): void
    {
        $mp = $this->submit(990000);

        $this->assertSame('waemu', $mp->market_code);
        $this->assertSame('monthly', $mp->detected_interval);
        $this->assertSame(990000, $mp->target_amount_minor);
        $this->assertSame('matched', $mp->resolution_status);
        $this->assertSame(ManualPayment::STATUS_PENDING, $mp->status);
        $this->assertSame(0, Subscription::withoutTenantScope()->where('tenant_id', $this->tenant->id)->where('plan_id', $this->essential->id)->count());
    }

    #[Test]
    public function approve_full_monthly_activates_subscription(): void
    {
        $mp = $this->submit(990000);
        $this->svc->approve($mp, $this->admin);

        $sub = $this->sub();
        $this->assertSame('active', $sub->status);
        $this->assertSame('monthly', $sub->interval);
        $this->assertSame('XOF', $sub->currency);
        $this->assertSame('waemu', $sub->market_code);
        $this->assertSame(990000, $sub->amount_paid_minor);
        $this->assertNotNull($sub->current_period_end);
    }

    #[Test]
    public function approve_full_yearly_activates_yearly(): void
    {
        $mp = $this->submit(9900000, 'yearly');
        $this->svc->approve($mp, $this->admin);

        $sub = $this->sub();
        $this->assertSame('active', $sub->status);
        $this->assertSame('yearly', $sub->interval);
        $this->assertSame(9900000, $sub->amount_paid_minor);
    }

    #[Test]
    public function approve_partial_sets_past_due_without_full_period(): void
    {
        $mp = $this->submit(495000, 'monthly');
        $this->svc->approve($mp, $this->admin);

        $sub = $this->sub();
        $this->assertSame('past_due', $sub->status);
        $this->assertSame(495000, $sub->amount_paid_minor);
        $this->assertNull($sub->current_period_end, 'la période ne court pas tant que non soldé');
        $this->assertSame('partial', $mp->fresh()->resolution_status);
        $this->assertSame(495000, $mp->fresh()->remaining_due_minor);
    }

    #[Test]
    public function two_deposits_accumulate_and_settle_to_active(): void
    {
        $d1 = $this->submit(495000, 'monthly');
        $this->svc->approve($d1, $this->admin);
        $this->assertSame('past_due', $this->sub()->status);

        $d2 = $this->submit(495000, 'monthly');
        $this->svc->approve($d2, $this->admin);

        $sub = $this->sub();
        $this->assertSame('active', $sub->status);
        $this->assertSame(990000, $sub->amount_paid_minor);
        // Le 1er acompte est clôturé (settled) → exclu d'un futur cumul.
        $this->assertSame('settled', $d1->fresh()->resolution_status);
        $this->assertSame('matched', $d2->fresh()->resolution_status);
    }

    #[Test]
    public function second_approve_is_idempotent_no_double_subscription(): void
    {
        $mp = $this->submit(990000);
        $this->svc->approve($mp, $this->admin);
        $this->svc->approve($mp->fresh(), $this->admin); // déjà approuvé → no-op

        $active = Subscription::withoutTenantScope()->where('tenant_id', $this->tenant->id)
            ->where('plan_id', $this->essential->id)->where('status', 'active')->get();
        $this->assertCount(1, $active);
        $this->assertSame(990000, $active->first()->amount_paid_minor, 'le cumul ne double pas');
    }

    #[Test]
    public function overpayment_beyond_target_traces_a_credit(): void
    {
        // 10 000 000 > borne haute annuelle → soldé annuel + avoir de 100 000.
        $mp = $this->submit(10000000, 'yearly');
        $this->svc->approve($mp, $this->admin);

        $sub = $this->sub();
        $this->assertSame('active', $sub->status);
        $this->assertSame('yearly', $sub->interval);
        $this->assertSame(100000, $sub->metadata['overpaid_minor'] ?? null);
        $this->assertSame(100000, $mp->fresh()->overpaid_minor);
        $this->assertSame('overpaid', $mp->fresh()->resolution_status);
    }

    #[Test]
    public function a_renewal_after_settlement_is_not_counted_as_credit(): void
    {
        // 1er mois soldé.
        $this->svc->approve($this->submit(990000), $this->admin);
        $this->assertSame('active', $this->sub()->status);

        // Renouvellement mensuel : repart d'un cumul à zéro (le 1er cycle est soldé/exclu).
        $renew = $this->submit(990000);
        $this->svc->approve($renew, $this->admin);

        $this->assertSame('matched', $renew->fresh()->resolution_status, 'renouvellement = nouveau cycle, pas un trop-perçu');
        $this->assertSame(0, $renew->fresh()->overpaid_minor);
        $this->assertSame('active', $this->sub()->status);
        $this->assertSame(990000, $this->sub()->amount_paid_minor);
    }

    #[Test]
    public function unknown_currency_is_approved_without_activation(): void
    {
        $mp = $this->svc->submit($this->tenant, $this->essential, 990000, 'JPY', 'wire', null, null);
        $this->svc->approve($mp, $this->admin);

        $this->assertSame('approved', $mp->fresh()->status);
        $this->assertSame('unmatched', $mp->fresh()->resolution_status);
        $this->assertSame(0, Subscription::withoutTenantScope()->where('tenant_id', $this->tenant->id)
            ->where('plan_id', $this->essential->id)->whereIn('status', ['active', 'past_due'])->count());
    }

    #[Test]
    public function a_promo_routes_to_needs_review_without_activation(): void
    {
        $mp = $this->submit(800000, 'monthly', 'PROMO20');
        $this->svc->approve($mp, $this->admin);

        $this->assertSame('approved', $mp->fresh()->status);
        $this->assertSame('needs_review', $mp->fresh()->resolution_status);
        $this->assertSame(0, Subscription::withoutTenantScope()->where('tenant_id', $this->tenant->id)
            ->where('plan_id', $this->essential->id)->whereIn('status', ['active', 'past_due'])->count());
    }
}
