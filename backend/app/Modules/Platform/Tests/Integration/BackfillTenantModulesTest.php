<?php

namespace App\Modules\Platform\Tests\Integration;

use App\Modules\Billing\Models\Plan;
use App\Modules\Platform\Models\TenantModule;
use App\Modules\Platform\Services\ModuleRegistryService;
use App\Modules\Tenants\Models\Tenant;
use Database\Seeders\ErpModulesSeeder;
use Database\Seeders\PlanModulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * `tenants:backfill-modules` brings tenants created before fail-closed gating up to the
 * provisioned baseline so they are not locked out of every business module after deploy.
 */
class BackfillTenantModulesTest extends TestCase
{
    use RefreshDatabase;

    // Tenants must start UNPROVISIONED here to reproduce the legacy / lockout case.
    protected bool $autoProvisionModules = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ErpModulesSeeder::class);
        Plan::firstOrCreate(['code' => 'starter'], [
            'name' => 'Starter', 'price_monthly_cents' => 0, 'price_yearly_cents' => 0,
            'currency' => 'XOF', 'trial_days' => 14, 'is_active' => true, 'is_public' => true, 'sort_order' => 1,
        ]);
        $this->seed(PlanModulesSeeder::class); // starter includes all modules ('*')
    }

    private function makeTenant(string $slug, string $plan = 'starter'): Tenant
    {
        return Tenant::create(['name' => $slug, 'slug' => $slug, 'plan' => $plan, 'status' => 'active', 'settings' => []]);
    }

    #[Test]
    public function it_activates_modules_for_an_unprovisioned_tenant(): void
    {
        $tenant = $this->makeTenant('legacy-bf');
        $this->assertSame(0, TenantModule::where('tenant_id', $tenant->id)->count());

        $this->artisan('tenants:backfill-modules')->assertExitCode(0);

        $registry = app(ModuleRegistryService::class);
        $this->assertTrue($registry->tenantHasModule($tenant, 'catalog'));
        $this->assertTrue($registry->tenantHasModule($tenant, 'reports'));
    }

    #[Test]
    public function dry_run_writes_nothing(): void
    {
        $tenant = $this->makeTenant('legacy-dry');

        $this->artisan('tenants:backfill-modules', ['--dry-run' => true])->assertExitCode(0);

        $this->assertSame(0, TenantModule::where('tenant_id', $tenant->id)->count());
    }

    #[Test]
    public function it_is_idempotent_and_skips_already_complete_tenants(): void
    {
        $tenant = $this->makeTenant('legacy-idem');

        $this->artisan('tenants:backfill-modules')->assertExitCode(0);
        $after1 = TenantModule::where('tenant_id', $tenant->id)->count();
        $this->assertGreaterThan(0, $after1);

        // Second run must not error nor change the row count.
        $this->artisan('tenants:backfill-modules')->assertExitCode(0);
        $this->assertSame($after1, TenantModule::where('tenant_id', $tenant->id)->count());
    }

    #[Test]
    public function a_tenant_with_an_unknown_plan_still_gets_all_modules(): void
    {
        $tenant = $this->makeTenant('legacy-noplan', 'ghost-plan'); // no such Plan row

        $this->artisan('tenants:backfill-modules')->assertExitCode(0);

        $registry = app(ModuleRegistryService::class);
        $this->assertTrue($registry->tenantHasModule($tenant, 'catalog'));
    }
}
