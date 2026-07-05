<?php

namespace Tests;

use App\Modules\Platform\Models\ErpModule;
use App\Modules\Platform\Models\TenantModule;
use App\Modules\Tenants\Models\Tenant;
use Database\Seeders\ErpModulesSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Spatie\Permission\PermissionRegistrar;

abstract class TestCase extends BaseTestCase
{
    /**
     * Auto-provision every ERP module for each tenant created during a test.
     *
     * Module gating is fail-closed (security audit), so an unprovisioned tenant is denied
     * on every gated route. Real tenants are provisioned at registration; tests get the
     * same baseline automatically here. Tests that specifically assert the unprovisioned /
     * partially-provisioned posture (ModuleGatingTest, SecurityRemediationTest) set this to
     * false and manage `tenant_modules` themselves.
     */
    protected bool $autoProvisionModules = true;

    protected function setUp(): void
    {
        parent::setUp();

        // Clear Spatie Permission in-memory cache before each test.
        // Without this, role/permission data from a previous test (RefreshDatabase)
        // can bleed into the next test, causing spurious 403 errors.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Registered on the per-test (refreshed) event dispatcher → no cross-test leak.
        Tenant::created(function (Tenant $tenant): void {
            if ($this->autoProvisionModules) {
                $this->activateAllModules($tenant);
            }
        });
    }

    /**
     * Provision EVERY ERP module as active for a tenant.
     *
     * Module gating is fail-closed (security audit): a tenant with no `tenant_modules`
     * rows is denied on module-gated routes (catalog/inventory/orders/customers/payments/
     * delivery/suppliers/import_export/reports). Tests that exercise those routes call this
     * in setUp so the gate lets the request through — mirroring a real provisioned tenant.
     *
     * @param  object  $tenant  a Tenant model (or anything with an ->id)
     */
    protected function activateAllModules(object $tenant): void
    {
        if (ErpModule::count() === 0) {
            $this->seed(ErpModulesSeeder::class);
        }

        foreach (ErpModule::pluck('id') as $moduleId) {
            TenantModule::updateOrCreate(
                ['tenant_id' => $tenant->id, 'module_id' => $moduleId],
                ['status' => TenantModule::STATUS_ACTIVE, 'activated_at' => now()],
            );
        }
    }

    /**
     * RC-20 (P-5) — crée un VRAI client pour le tenant, avec un id imposé.
     *
     * `OrderService::create` valide désormais que `customer_id` appartient au tenant : les tests
     * qui passaient un UUID fictif (référence orpheline, tolérée avant) créent le client ici.
     */
    protected function seedCustomer(string $id, string $tenantId, string $name = 'Client Test'): void
    {
        $customer = new \App\Modules\Customers\Models\Customer([
            'tenant_id' => $tenantId,
            'name'      => $name,
            'email'     => substr($id, 0, 8) . '@client-test.sn',
        ]);
        $customer->id = $id;
        $customer->save();
    }
}
