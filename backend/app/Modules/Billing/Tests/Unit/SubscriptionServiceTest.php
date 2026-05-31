<?php

namespace App\Modules\Billing\Tests\Unit;

use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Services\SubscriptionService;
use App\Modules\Platform\Models\ErpModule;
use App\Modules\Platform\Models\TenantModule;
use App\Modules\Platform\Services\ModuleRegistryService;
use App\Modules\Tenants\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SubscriptionServiceTest extends TestCase
{
    use RefreshDatabase;

    private SubscriptionService $service;
    private Tenant $tenant;
    private Plan $starterPlan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = $this->app->make(SubscriptionService::class);

        $this->tenant = Tenant::create([
            'name'   => 'Test Company',
            'slug'   => 'test-company',
            'plan'   => 'starter',
            'status' => 'active',
        ]);

        $this->starterPlan = Plan::create([
            'code'                => Plan::CODE_STARTER,
            'name'                => 'Starter',
            'price_monthly_cents' => 0,
            'price_yearly_cents'  => 0,
            'currency'            => 'XOF',
            'trial_days'          => 14,
            'is_active'           => true,
            'is_public'           => true,
            'sort_order'          => 1,
        ]);
    }

    #[Test]
    public function it_creates_a_trialing_subscription_for_starter(): void
    {
        $subscription = $this->service->createStarter($this->tenant);

        $this->assertInstanceOf(Subscription::class, $subscription);
        $this->assertSame(Subscription::STATUS_TRIALING, $subscription->status);
        $this->assertSame($this->tenant->id, $subscription->tenant_id);
        $this->assertSame($this->starterPlan->id, $subscription->plan_id);
        $this->assertNotNull($subscription->trial_ends_at);
    }

    #[Test]
    public function it_updates_tenant_plan_and_status_after_starter_subscription(): void
    {
        $this->service->createStarter($this->tenant);

        $this->tenant->refresh();

        $this->assertSame(Plan::CODE_STARTER, $this->tenant->plan);
        $this->assertSame(Subscription::STATUS_TRIALING, $this->tenant->subscription_status);
    }

    #[Test]
    public function it_activates_plan_modules_on_starter_creation(): void
    {
        // Create a module linked to the starter plan
        $module = ErpModule::create([
            'code'        => 'catalog',
            'name'        => 'Catalogue',
            'category'    => ErpModule::CATEGORY_OPERATIONS,
            'status'      => ErpModule::STATUS_ACTIVE,
            'is_core'     => false,
            'is_visible'  => true,
            'sort_order'  => 1,
        ]);

        $this->starterPlan->modules()->attach($module->id, ['is_included' => true, 'limits' => null]);

        $this->service->createStarter($this->tenant);

        $this->assertDatabaseHas('tenant_modules', [
            'tenant_id' => $this->tenant->id,
            'module_id' => $module->id,
            'status'    => TenantModule::STATUS_ACTIVE,
        ]);
    }

    #[Test]
    public function current_returns_the_most_recent_non_cancelled_subscription(): void
    {
        $sub1 = Subscription::create([
            'tenant_id'  => $this->tenant->id,
            'plan_id'    => $this->starterPlan->id,
            'status'     => Subscription::STATUS_CANCELLED,
            'cancelled_at' => now()->subMonth(),
            'current_period_start' => now()->subMonth(),
            'current_period_end'   => now()->subMonth(),
        ]);

        $sub2 = Subscription::create([
            'tenant_id' => $this->tenant->id,
            'plan_id'   => $this->starterPlan->id,
            'status'    => Subscription::STATUS_TRIALING,
            'current_period_start' => now(),
            'current_period_end'   => now()->addDays(14),
        ]);

        $current = $this->service->current($this->tenant);

        $this->assertNotNull($current);
        $this->assertSame($sub2->id, $current->id);
    }

    #[Test]
    public function suspend_sets_subscription_and_tenant_status(): void
    {
        Subscription::create([
            'tenant_id' => $this->tenant->id,
            'plan_id'   => $this->starterPlan->id,
            'status'    => Subscription::STATUS_ACTIVE,
            'current_period_start' => now(),
            'current_period_end'   => now()->addMonth(),
        ]);

        $this->service->suspend($this->tenant, 'Non-paiement');

        // current() excludes only cancelled; suspended are still returned
        $sub = $this->service->current($this->tenant);
        $this->assertNotNull($sub);
        $this->assertSame(Subscription::STATUS_SUSPENDED, $sub->status);

        $this->tenant->refresh();
        $this->assertSame('suspended', $this->tenant->status);
        $this->assertSame(Subscription::STATUS_SUSPENDED, $this->tenant->subscription_status);
    }

    #[Test]
    public function change_plan_cancels_current_and_creates_new(): void
    {
        $this->service->createStarter($this->tenant);

        $proPlan = Plan::create([
            'code'                => Plan::CODE_PRO,
            'name'                => 'Pro',
            'price_monthly_cents' => 1500000,
            'price_yearly_cents'  => 15000000,
            'currency'            => 'XOF',
            'trial_days'          => 14,
            'is_active'           => true,
            'is_public'           => true,
            'sort_order'          => 2,
        ]);

        $this->service->changePlan($this->tenant, $proPlan);

        $this->tenant->refresh();
        $this->assertSame(Plan::CODE_PRO, $this->tenant->plan);

        // Old subscription should be cancelled
        $this->assertDatabaseHas('subscriptions', [
            'tenant_id' => $this->tenant->id,
            'plan_id'   => $this->starterPlan->id,
            'status'    => Subscription::STATUS_CANCELLED,
        ]);

        // New subscription exists
        $this->assertDatabaseHas('subscriptions', [
            'tenant_id' => $this->tenant->id,
            'plan_id'   => $proPlan->id,
        ]);
    }
}
