<?php

namespace App\Modules\Platform\Tests\Integration;

use App\Models\User;
use App\Modules\Billing\Models\Plan;
use App\Modules\Platform\Services\ModuleRegistryService;
use App\Modules\Tenants\Models\Tenant;
use Database\Seeders\ErpModulesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * RBAC Phase A — a tenant module gates its routes at the backend. Removing a module
 * neutralises access for EVERY user of the tenant (admins included), not just menus.
 */
class ModuleGatingTest extends TestCase
{
    use RefreshDatabase;

    // This suite drives module provisioning explicitly to assert the gate.
    protected bool $autoProvisionModules = false;

    private Tenant $tenant;
    private string $token;
    private ModuleRegistryService $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ErpModulesSeeder::class);
        Plan::firstOrCreate(['code' => 'starter'], ['name' => 'Starter', 'price_monthly_cents' => 0, 'price_yearly_cents' => 0, 'currency' => 'XOF', 'trial_days' => 14, 'is_active' => true, 'is_public' => true, 'sort_order' => 1]);
        $this->registry = app(ModuleRegistryService::class);

        $this->tenant = Tenant::create(['name' => 'T', 'slug' => 'mod-gate', 'plan' => 'starter', 'status' => 'active', 'settings' => []]);
        $admin = User::create(['name' => 'A', 'email' => 'a@mod-gate.sn', 'password' => bcrypt('x'), 'tenant_id' => $this->tenant->id]);
        $admin->assignTenantRole('admin'); // strongest tenant role — still gated by module
        $this->token = $admin->createToken('api')->plainTextToken;
    }

    private function getReports()
    {
        return $this->withToken($this->token)->getJson('/api/reports/sales');
    }

    #[Test]
    public function an_unprovisioned_tenant_is_fail_closed(): void
    {
        // Security remediation: missing tenant_modules rows must never unlock paid modules.
        $this->getReports()->assertStatus(403)->assertJsonPath('module', 'reports');
    }

    #[Test]
    public function a_provisioned_tenant_without_the_module_is_denied(): void
    {
        $this->registry->activate($this->tenant, 'catalog'); // provisioned, but NOT reports

        $this->getReports()->assertStatus(403)->assertJsonPath('module', 'reports');
    }

    #[Test]
    public function activating_the_module_grants_access(): void
    {
        $this->registry->activate($this->tenant, 'reports');

        $this->getReports()->assertOk();
    }

    #[Test]
    public function removing_a_module_revokes_access_even_for_an_admin(): void
    {
        $this->registry->activate($this->tenant, 'reports');
        $this->getReports()->assertOk();

        $this->registry->deactivate($this->tenant, 'reports');
        $this->getReports()->assertStatus(403); // the admin loses access too
    }
}
