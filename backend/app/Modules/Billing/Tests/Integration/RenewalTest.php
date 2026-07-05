<?php

namespace App\Modules\Billing\Tests\Integration;

use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Services\RenewalService;
use App\Modules\Tenants\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * RC-5J — renouvellement & relance : rappels idempotents, past_due à échéance, roulement des plans
 * gratuits, suspension après grâce, acomptes échelonnés épargnés.
 */
class RenewalTest extends TestCase
{
    use RefreshDatabase;

    private Plan $paid;
    private Plan $free;

    protected function setUp(): void
    {
        parent::setUp();
        $this->free = Plan::firstOrCreate(['code' => 'starter'], ['name' => 'Starter', 'price_monthly_cents' => 0, 'price_yearly_cents' => 0, 'currency' => 'XOF', 'trial_days' => 14, 'is_active' => true, 'is_public' => true, 'sort_order' => 1]);
        $this->paid = Plan::firstOrCreate(['code' => 'pro'], ['name' => 'Pro', 'price_monthly_cents' => 1500000, 'price_yearly_cents' => 15000000, 'currency' => 'XOF', 'trial_days' => 0, 'is_active' => true, 'is_public' => true, 'sort_order' => 2]);
    }

    private function tenant(string $slug): Tenant
    {
        return Tenant::create(['name' => $slug, 'slug' => $slug, 'plan' => 'starter', 'status' => 'active', 'settings' => []]);
    }

    private function sub(Tenant $t, Plan $plan, string $status, ?\Carbon\Carbon $periodEnd, array $extra = []): Subscription
    {
        return Subscription::create(array_merge([
            'tenant_id'            => $t->id,
            'plan_id'              => $plan->id,
            'status'               => $status,
            'interval'             => Subscription::INTERVAL_MONTHLY,
            'current_period_start' => $periodEnd?->copy()->subMonth(),
            'current_period_end'   => $periodEnd,
        ], $extra));
    }

    private function process(): array
    {
        return $this->app->make(RenewalService::class)->processRenewals();
    }

    #[Test]
    public function an_expired_paid_subscription_becomes_past_due(): void
    {
        $t   = $this->tenant('exp-paid');
        $sub = $this->sub($t, $this->paid, Subscription::STATUS_ACTIVE, now()->subDay());

        $summary = $this->process();

        $this->assertSame(1, $summary['past_due']);
        $this->assertSame(Subscription::STATUS_PAST_DUE, $sub->fresh()->status);
        $this->assertSame(Subscription::STATUS_PAST_DUE, $t->fresh()->subscription_status);
        $this->assertDatabaseHas('audit_logs', ['tenant_id' => $t->id, 'action' => 'billing.renewal_overdue']);
    }

    #[Test]
    public function an_expired_free_subscription_rolls_its_period_and_stays_active(): void
    {
        $t   = $this->tenant('exp-free');
        $end = now()->subDay()->startOfSecond();
        $sub = $this->sub($t, $this->free, Subscription::STATUS_ACTIVE, $end);

        $summary = $this->process();

        $this->assertSame(1, $summary['rolled']);
        $fresh = $sub->fresh();
        $this->assertSame(Subscription::STATUS_ACTIVE, $fresh->status);
        $this->assertTrue($fresh->current_period_end->equalTo($end->copy()->addMonth()));
    }

    #[Test]
    public function a_past_due_beyond_grace_is_suspended_and_within_grace_is_not(): void
    {
        $tOld = $this->tenant('grace-out');
        $this->sub($tOld, $this->paid, Subscription::STATUS_PAST_DUE, now()->subDays(RenewalService::GRACE_DAYS + 2));

        $tNew = $this->tenant('grace-in');
        $subNew = $this->sub($tNew, $this->paid, Subscription::STATUS_PAST_DUE, now()->subDays(2));

        $summary = $this->process();

        $this->assertSame(1, $summary['suspended']);
        $this->assertSame('suspended', $tOld->fresh()->status);
        $this->assertSame(Subscription::STATUS_PAST_DUE, $subNew->fresh()->status); // grâce en cours
    }

    #[Test]
    public function an_installment_past_due_with_no_period_is_never_suspended(): void
    {
        // Acompte échelonné (RC-1C) : past_due avec période JAMAIS démarrée (end null).
        $t   = $this->tenant('installment');
        $sub = $this->sub($t, $this->paid, Subscription::STATUS_PAST_DUE, null);

        $summary = $this->process();

        $this->assertSame(0, $summary['suspended']);
        $this->assertSame(Subscription::STATUS_PAST_DUE, $sub->fresh()->status);
    }

    #[Test]
    public function reminders_are_sent_per_bucket_and_are_idempotent(): void
    {
        $t   = $this->tenant('remind');
        $sub = $this->sub($t, $this->paid, Subscription::STATUS_ACTIVE, now()->addDays(2)); // bucket J-3

        $first  = $this->process();
        $second = $this->process();

        $this->assertSame(1, $first['reminders']);
        $this->assertSame(0, $second['reminders']); // idempotent pour le même bucket
        $this->assertContains(3, $sub->fresh()->metadata['renewal_reminders']);
        $this->assertDatabaseHas('audit_logs', ['tenant_id' => $t->id, 'action' => 'billing.renewal_reminder']);
    }

    #[Test]
    public function free_plans_never_get_reminders(): void
    {
        $t = $this->tenant('free-remind');
        $this->sub($t, $this->free, Subscription::STATUS_ACTIVE, now()->addDays(2));

        $this->assertSame(0, $this->process()['reminders']);
    }

    #[Test]
    public function the_artisan_command_runs_and_reports(): void
    {
        $t = $this->tenant('cmd');
        $this->sub($t, $this->paid, Subscription::STATUS_ACTIVE, now()->subDay());

        $this->artisan('billing:process-renewals')
            ->expectsOutputToContain('past_due: 1')
            ->assertSuccessful();
    }
}
