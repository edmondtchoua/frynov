<?php
namespace App\Modules\Billing\Tests\Unit;

use App\Models\User;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Services\QuotaService;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Tenants\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class QuotaServiceTest extends TestCase
{
    use RefreshDatabase;

    private QuotaService $svc;
    private Plan $plan;
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->svc    = app(QuotaService::class);
        $this->plan   = Plan::firstOrCreate(['code' => 'starter'], [
            'name' => 'Starter', 'price_monthly_cents' => 0, 'price_yearly_cents' => 0,
            'currency' => 'XOF', 'trial_days' => 14, 'is_active' => true, 'is_public' => true, 'sort_order' => 1,
            'max_users' => 3, 'max_warehouses' => 1, 'max_agents' => 2, 'max_products' => 50,
        ]);
        $this->tenant = Tenant::create(['name' => 'Q', 'slug' => 'quota-t', 'plan' => 'starter', 'status' => 'active', 'settings' => []]);
    }

    #[Test]
    public function can_add_user_below_quota(): void
    {
        User::create(['name' => 'U1', 'email' => 'u1@q.sn', 'password' => bcrypt('x'), 'tenant_id' => $this->tenant->id]);
        User::create(['name' => 'U2', 'email' => 'u2@q.sn', 'password' => bcrypt('x'), 'tenant_id' => $this->tenant->id]);
        // 2 users, max 3 — should pass
        $this->svc->assertCanAddUser($this->tenant);
        $this->assertTrue(true);
    }

    #[Test]
    public function cannot_add_user_over_quota(): void
    {
        for ($i = 1; $i <= 3; $i++) {
            User::create(['name' => "U{$i}", 'email' => "u{$i}@q.sn", 'password' => bcrypt('x'), 'tenant_id' => $this->tenant->id]);
        }
        $this->expectException(\DomainException::class);
        $this->svc->assertCanAddUser($this->tenant);
    }

    #[Test]
    public function can_add_warehouse_below_quota(): void
    {
        $this->svc->assertCanAddWarehouse($this->tenant);
        $this->assertTrue(true);
    }

    #[Test]
    public function cannot_add_warehouse_over_quota(): void
    {
        Warehouse::create(['tenant_id' => $this->tenant->id, 'name' => 'W', 'code' => 'WQ1', 'is_default' => true]);
        $this->expectException(\DomainException::class);
        $this->svc->assertCanAddWarehouse($this->tenant);
    }

    #[Test]
    public function unlimited_quota_never_throws(): void
    {
        $entPlan  = Plan::firstOrCreate(['code' => 'enterprise'], [
            'name' => 'Enterprise', 'price_monthly_cents' => 0, 'price_yearly_cents' => 0,
            'currency' => 'XOF', 'trial_days' => 0, 'is_active' => true, 'is_public' => false, 'sort_order' => 99,
            'max_users' => null, 'max_warehouses' => null,
        ]);
        $entTenant = Tenant::create(['name' => 'E', 'slug' => 'ent-q', 'plan' => 'enterprise', 'status' => 'active', 'settings' => []]);

        // Null = unlimited — must never throw
        for ($i = 0; $i < 100; $i++) {
            $this->svc->assertCanAddUser($entTenant);
            $this->svc->assertCanAddWarehouse($entTenant);
        }
        $this->assertTrue(true);
    }

    #[Test]
    public function zero_limit_means_unlimited_like_the_seeded_enterprise_plan(): void
    {
        // The seeded Enterprise plan stores 0 (not null) for max_products / max_users /
        // max_monthly_orders. 0 must be treated as unlimited, otherwise the most
        // expensive tier cannot create a single product, user, or order.
        $entPlan = Plan::firstOrCreate(['code' => 'enterprise'], [
            'name' => 'Enterprise', 'price_monthly_cents' => 0, 'price_yearly_cents' => 0,
            'currency' => 'XOF', 'trial_days' => 0, 'is_active' => true, 'is_public' => false, 'sort_order' => 99,
        ]);
        $entPlan->update([
            'max_users' => 0, 'max_products' => 0, 'max_monthly_orders' => 0,
            'max_warehouses' => null, 'max_agents' => null,
        ]);
        $entTenant = Tenant::create(['name' => 'E0', 'slug' => 'ent-zero', 'plan' => 'enterprise', 'status' => 'active', 'settings' => []]);

        // Must NOT throw even with existing resources (0 = unlimited, not "zero allowed")
        $this->svc->assertCanAddUser($entTenant);
        $this->svc->assertCanAddProduct($entTenant);
        $this->svc->assertCanCreateOrder($entTenant);
        $this->assertTrue(true);
    }

    #[Test]
    public function plan_limits_take_precedence_over_legacy_plan_columns(): void
    {
        // Legacy column is generous (5); the canonical plan_limits row is strict (1).
        // QuotaService must enforce plan_limits (1), not the legacy column (5).
        $this->plan->update(['max_warehouses' => 5]);
        $this->plan->limits()->create(['max_warehouses' => 1]);

        Warehouse::create(['tenant_id' => $this->tenant->id, 'name' => 'W1', 'code' => 'WQP1', 'is_default' => true]);

        // Already at the plan_limits ceiling (1) → must throw despite the legacy column allowing 5.
        $this->expectException(\DomainException::class);
        $this->svc->assertCanAddWarehouse($this->tenant);
    }
}
