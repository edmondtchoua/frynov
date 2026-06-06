<?php

namespace App\Modules\Auth\Tests\Integration;

use App\Models\User;
use App\Modules\Auth\Services\TenantRoleService;
use App\Modules\Billing\Models\Plan;
use App\Modules\Platform\Services\ModuleRegistryService;
use App\Modules\Tenants\Models\Tenant;
use Database\Seeders\ErpModulesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * RBAC Phase B2 — tenant-configurable custom roles with plan-bounded permissions.
 */
class TenantRoleTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $member;
    private string $adminToken;
    private string $memberToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ErpModulesSeeder::class);
        Plan::firstOrCreate(['code' => 'starter'], ['name' => 'Starter', 'price_monthly_cents' => 0, 'price_yearly_cents' => 0, 'currency' => 'XOF', 'trial_days' => 14, 'is_active' => true, 'is_public' => true, 'sort_order' => 1]);

        $this->tenant = Tenant::create(['name' => 'T', 'slug' => 'roles-t', 'plan' => 'starter', 'status' => 'active', 'settings' => []]);
        $admin = User::create(['name' => 'A', 'email' => 'a@roles-t.sn', 'password' => bcrypt('x'), 'tenant_id' => $this->tenant->id]);
        $admin->assignTenantRole('admin');
        $this->adminToken = $admin->createToken('api')->plainTextToken;

        $this->member = User::create(['name' => 'M', 'email' => 'm@roles-t.sn', 'password' => bcrypt('x'), 'tenant_id' => $this->tenant->id]);
        $this->member->assignTenantRole('member');
        $this->memberToken = $this->member->createToken('api')->plainTextToken;
    }

    private function admin(): array { return ['Authorization' => "Bearer {$this->adminToken}"]; }

    #[Test]
    public function admin_creates_a_custom_role_with_only_bounded_permissions(): void
    {
        $perms = $this->postJson('/api/workspace/roles', [
            'name'        => 'Resp dépôt',
            'permissions' => ['products.view', 'orders.create', 'admin.access', 'reports.view'],
        ], $this->admin())->assertCreated()->json('permissions');

        $this->assertContains('products.view', $perms);
        $this->assertContains('orders.create', $perms);
        $this->assertNotContains('admin.access', $perms);  // blocked platform permission
        $this->assertNotContains('reports.view', $perms);  // optional module 'reports' inactive → not grantable
    }

    #[Test]
    public function an_active_optional_module_unlocks_its_permissions(): void
    {
        app(ModuleRegistryService::class)->activate($this->tenant, 'reports');

        $perms = $this->postJson('/api/workspace/roles', ['name' => 'Analyste', 'permissions' => ['reports.view']], $this->admin())
            ->assertCreated()->json('permissions');

        $this->assertContains('reports.view', $perms);
    }

    #[Test]
    public function admin_lists_base_and_custom_roles_plus_the_grantable_catalogue(): void
    {
        $this->postJson('/api/workspace/roles', ['name' => 'Custom X', 'permissions' => ['orders.create']], $this->admin());

        $res   = $this->getJson('/api/workspace/roles', $this->admin())->assertOk();
        $names = collect($res->json('data'))->pluck('name');

        $this->assertTrue($names->contains('manager'));               // base role
        $this->assertTrue($names->contains('Custom X'));              // custom role
        $this->assertNotContains('admin.access', $res->json('grantable')); // bounded
        $this->assertContains('orders.create', $res->json('grantable'));
    }

    #[Test]
    public function a_non_admin_cannot_manage_roles(): void
    {
        $member = ['Authorization' => "Bearer {$this->memberToken}"];
        $this->getJson('/api/workspace/roles', $member)->assertStatus(403);
        $this->postJson('/api/workspace/roles', ['name' => 'X'], $member)->assertStatus(403);
    }

    #[Test]
    public function a_tenant_cannot_modify_another_tenants_role(): void
    {
        // Create tenant A's role via the service so the only authenticated HTTP request
        // is tenant B's (avoids the auth-guard caching a prior user across sub-requests).
        $role = app(TenantRoleService::class)->create($this->tenant, 'Mine', []);

        $other  = Tenant::create(['name' => 'O', 'slug' => 'roles-o', 'plan' => 'starter', 'status' => 'active', 'settings' => []]);
        $oAdmin = User::create(['name' => 'OA', 'email' => 'oa@roles-o.sn', 'password' => bcrypt('x'), 'tenant_id' => $other->id]);
        $oAdmin->assignTenantRole('admin');
        $oToken = $oAdmin->createToken('api')->plainTextToken;

        $this->patchJson("/api/workspace/roles/{$role->id}", ['name' => 'Hacked'], ['Authorization' => "Bearer {$oToken}"])
            ->assertStatus(403);
    }

    #[Test]
    public function admin_assigns_a_custom_role_to_a_member_then_deletes_it(): void
    {
        $role = $this->postJson('/api/workspace/roles', ['name' => 'Caissier WE', 'permissions' => ['orders.create']], $this->admin())->json();

        $this->patchJson("/api/workspace/users/{$this->member->id}", ['role' => 'Caissier WE'], $this->admin())->assertOk();

        app(PermissionRegistrar::class)->setPermissionsTeamId($this->tenant->id);
        $this->assertTrue($this->member->fresh()->hasRole('Caissier WE'));

        $this->deleteJson("/api/workspace/roles/{$role['id']}", [], $this->admin())->assertOk();
        $this->assertDatabaseMissing('roles', ['id' => $role['id']]);
    }
}
