<?php

namespace App\Modules\Sync\Tests\Modular;

use App\Modules\Sync\Models\Sync;
use App\Modules\Sync\Repositories\EloquentSyncRepository;
use App\Modules\Sync\Repositories\SyncRepositoryInterface;
use App\Modules\Sync\Services\SyncService;
use App\Modules\Tenants\Models\Tenant;
use App\Shared\Scopes\TenantScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Module-wiring tests for the Sync module:
 *   - the repository interface is bound to its Eloquent implementation
 *   - the service is resolvable from the container
 *   - the API routes are registered under the /api prefix
 *   - tenant isolation is enforced at the model layer via the global TenantScope
 */
class SyncModuleTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_repository_interface_is_bound_to_the_eloquent_implementation(): void
    {
        $this->assertInstanceOf(
            EloquentSyncRepository::class,
            $this->app->make(SyncRepositoryInterface::class),
        );
    }

    #[Test]
    public function the_service_is_resolvable_from_the_container(): void
    {
        $this->assertInstanceOf(SyncService::class, $this->app->make(SyncService::class));
    }

    #[Test]
    public function the_module_registers_its_api_routes(): void
    {
        $uris = collect(Route::getRoutes()->getRoutes())
            ->map(fn ($route) => $route->uri())
            ->all();

        $this->assertContains('api/syncs', $uris);
        $this->assertContains('api/syncs/{sync}', $uris);
    }

    #[Test]
    public function the_write_routes_are_guarded_by_the_manager_or_admin_role(): void
    {
        $store = Route::getRoutes()->getByName('syncs.store');

        $this->assertNotNull($store, 'The syncs.store route should be registered.');
        $this->assertContains('role:manager|admin', $store->gatherMiddleware());
    }

    #[Test]
    public function the_model_registers_the_global_tenant_scope(): void
    {
        $this->assertArrayHasKey(TenantScope::class, (new Sync())->getGlobalScopes());
    }

    #[Test]
    public function the_tenant_scope_isolates_rows_per_tenant(): void
    {
        $tenantA = Tenant::create(['name' => 'A', 'slug' => 'mod-a', 'plan' => 'starter', 'status' => 'active']);
        $tenantB = Tenant::create(['name' => 'B', 'slug' => 'mod-b', 'plan' => 'starter', 'status' => 'active']);

        Sync::withoutTenantScope()->create(['tenant_id' => $tenantA->id]);
        Sync::withoutTenantScope()->create(['tenant_id' => $tenantA->id]);
        Sync::withoutTenantScope()->create(['tenant_id' => $tenantB->id]);

        // Bind tenant A as the current tenant → the global scope must filter to A only.
        $this->app->instance('current.tenant.id', $tenantA->id);

        $this->assertSame(2, Sync::count());
        $this->assertSame(3, Sync::withoutTenantScope()->count());
    }
}
