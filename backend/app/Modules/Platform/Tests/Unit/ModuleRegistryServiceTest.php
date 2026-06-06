<?php

namespace App\Modules\Platform\Tests\Unit;

use App\Modules\Billing\Models\Plan;
use App\Modules\Platform\Models\ErpModule;
use App\Modules\Platform\Models\TenantModule;
use App\Modules\Platform\Services\ModuleRegistryService;
use App\Modules\Tenants\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ModuleRegistryServiceTest extends TestCase
{
    use RefreshDatabase;

    private ModuleRegistryService $service;
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = $this->app->make(ModuleRegistryService::class);

        $this->tenant = Tenant::create([
            'name'   => 'Demo Shop',
            'slug'   => 'demo-shop',
            'plan'   => 'starter',
            'status' => 'active',
        ]);
    }

    private function makeModule(string $code, bool $isCore = false, bool $isVisible = true): ErpModule
    {
        return ErpModule::create([
            'code'       => $code,
            'name'       => ucfirst($code),
            'category'   => ErpModule::CATEGORY_OPERATIONS,
            'status'     => ErpModule::STATUS_ACTIVE,
            'is_core'    => $isCore,
            'is_visible' => $isVisible,
            'sort_order' => 1,
        ]);
    }

    #[Test]
    public function list_for_tenant_returns_visible_modules_with_status(): void
    {
        $catalog   = $this->makeModule('catalog');
        $inventory = $this->makeModule('inventory');
        $this->makeModule('hidden', isVisible: false);

        TenantModule::create([
            'tenant_id'    => $this->tenant->id,
            'module_id'    => $catalog->id,
            'status'       => TenantModule::STATUS_ACTIVE,
            'activated_at' => now(),
        ]);

        $list = $this->service->listForTenant($this->tenant);

        // Hidden module excluded
        $this->assertCount(2, $list);

        $catalogResult = $list->firstWhere('code', 'catalog');
        $this->assertTrue($catalogResult->tenant_active);
        $this->assertSame(TenantModule::STATUS_ACTIVE, $catalogResult->tenant_status);

        $inventoryResult = $list->firstWhere('code', 'inventory');
        $this->assertFalse($inventoryResult->tenant_active);
        $this->assertNull($inventoryResult->tenant_status);
    }

    #[Test]
    public function active_codes_includes_core_modules_always(): void
    {
        $this->makeModule('dashboard', isCore: true);
        $this->makeModule('catalog',   isCore: false);

        $codes = $this->service->activeCodes($this->tenant);

        $this->assertContains('dashboard', $codes);
        $this->assertNotContains('catalog', $codes);
    }

    #[Test]
    public function active_codes_includes_activated_non_core_modules(): void
    {
        $core    = $this->makeModule('dashboard', isCore: true);
        $catalog = $this->makeModule('catalog',   isCore: false);

        TenantModule::create([
            'tenant_id'    => $this->tenant->id,
            'module_id'    => $catalog->id,
            'status'       => TenantModule::STATUS_ACTIVE,
            'activated_at' => now(),
        ]);

        $codes = $this->service->activeCodes($this->tenant);

        $this->assertContains('dashboard', $codes);
        $this->assertContains('catalog',   $codes);
    }

    #[Test]
    public function tenant_has_module_returns_true_for_core(): void
    {
        $this->makeModule('dashboard', isCore: true);

        $this->assertTrue($this->service->tenantHasModule($this->tenant, 'dashboard'));
    }

    #[Test]
    public function tenant_has_module_returns_false_when_not_activated(): void
    {
        $this->makeModule('catalog');

        $this->assertFalse($this->service->tenantHasModule($this->tenant, 'catalog'));
    }

    #[Test]
    public function tenant_has_module_returns_true_when_active(): void
    {
        $mod = $this->makeModule('catalog');

        TenantModule::create([
            'tenant_id'    => $this->tenant->id,
            'module_id'    => $mod->id,
            'status'       => TenantModule::STATUS_ACTIVE,
            'activated_at' => now(),
        ]);

        $this->assertTrue($this->service->tenantHasModule($this->tenant, 'catalog'));
    }

    #[Test]
    public function tenant_has_module_returns_true_for_trial(): void
    {
        $mod = $this->makeModule('catalog');

        TenantModule::create([
            'tenant_id'    => $this->tenant->id,
            'module_id'    => $mod->id,
            'status'       => TenantModule::STATUS_TRIAL,
            'activated_at' => now(),
        ]);

        $this->assertTrue($this->service->tenantHasModule($this->tenant, 'catalog'));
    }

    #[Test]
    public function activate_creates_or_updates_tenant_module(): void
    {
        $this->makeModule('catalog');

        $tenantModule = $this->service->activate($this->tenant, 'catalog');

        $this->assertSame(TenantModule::STATUS_ACTIVE, $tenantModule->status);
        $this->assertSame($this->tenant->id, $tenantModule->tenant_id);
    }

    #[Test]
    public function deactivate_sets_status_to_inactive(): void
    {
        $mod = $this->makeModule('catalog');

        TenantModule::create([
            'tenant_id'    => $this->tenant->id,
            'module_id'    => $mod->id,
            'status'       => TenantModule::STATUS_ACTIVE,
            'activated_at' => now(),
        ]);

        $this->service->deactivate($this->tenant, 'catalog');

        $this->assertDatabaseHas('tenant_modules', [
            'tenant_id' => $this->tenant->id,
            'module_id' => $mod->id,
            'status'    => TenantModule::STATUS_INACTIVE,
        ]);
    }

    #[Test]
    public function deactivate_cannot_deactivate_core_module(): void
    {
        $core = $this->makeModule('dashboard', isCore: true);

        TenantModule::create([
            'tenant_id'    => $this->tenant->id,
            'module_id'    => $core->id,
            'status'       => TenantModule::STATUS_ACTIVE,
            'activated_at' => now(),
        ]);

        $this->service->deactivate($this->tenant, 'dashboard');

        // Should still be active
        $this->assertDatabaseHas('tenant_modules', [
            'tenant_id' => $this->tenant->id,
            'module_id' => $core->id,
            'status'    => TenantModule::STATUS_ACTIVE,
        ]);
    }

    #[Test]
    public function activate_plan_modules_activates_all_included_modules(): void
    {
        $catalog   = $this->makeModule('catalog');
        $inventory = $this->makeModule('inventory');

        $plan = Plan::create([
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

        $plan->modules()->attach($catalog->id,   ['is_included' => true,  'limits' => null]);
        $plan->modules()->attach($inventory->id, ['is_included' => false, 'limits' => null]);

        $this->service->activatePlanModules($this->tenant, $plan);

        $this->assertDatabaseHas('tenant_modules', [
            'tenant_id' => $this->tenant->id,
            'module_id' => $catalog->id,
            'status'    => TenantModule::STATUS_ACTIVE,
        ]);

        // Not included → should not be activated
        $this->assertDatabaseMissing('tenant_modules', [
            'tenant_id' => $this->tenant->id,
            'module_id' => $inventory->id,
        ]);
    }

    #[Test]
    public function downgrade_deactivates_modules_not_in_the_new_plan(): void
    {
        $catalog  = $this->makeModule('catalog');
        $payments = $this->makeModule('payments');

        $pro = Plan::create([
            'code' => 'pro-d', 'name' => 'Pro', 'price_monthly_cents' => 0, 'price_yearly_cents' => 0,
            'currency' => 'XOF', 'trial_days' => 0, 'is_active' => true, 'is_public' => true, 'sort_order' => 2,
        ]);
        $pro->modules()->attach($catalog->id,  ['is_included' => true, 'limits' => null]);
        $pro->modules()->attach($payments->id, ['is_included' => true, 'limits' => null]);

        $starter = Plan::create([
            'code' => 'starter-d', 'name' => 'Starter', 'price_monthly_cents' => 0, 'price_yearly_cents' => 0,
            'currency' => 'XOF', 'trial_days' => 0, 'is_active' => true, 'is_public' => true, 'sort_order' => 1,
        ]);
        $starter->modules()->attach($catalog->id, ['is_included' => true, 'limits' => null]);

        // Tenant on Pro: both modules active
        $this->service->activatePlanModules($this->tenant, $pro);
        $this->assertDatabaseHas('tenant_modules', [
            'tenant_id' => $this->tenant->id, 'module_id' => $payments->id, 'status' => TenantModule::STATUS_ACTIVE,
        ]);

        // Downgrade to Starter: payments must be deactivated, catalog stays active
        $this->service->activatePlanModules($this->tenant, $starter);
        $this->assertDatabaseHas('tenant_modules', [
            'tenant_id' => $this->tenant->id, 'module_id' => $catalog->id, 'status' => TenantModule::STATUS_ACTIVE,
        ]);
        $this->assertDatabaseHas('tenant_modules', [
            'tenant_id' => $this->tenant->id, 'module_id' => $payments->id, 'status' => TenantModule::STATUS_INACTIVE,
        ]);
    }
}
