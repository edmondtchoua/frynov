<?php

namespace App\Modules\Inventory\Tests\Unit;

use App\Models\User;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Support\WarehouseScope;
use App\Modules\Tenants\Models\Tenant;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WarehouseScopeTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Warehouse $whA;
    private Warehouse $whB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->tenant = Tenant::create(['name' => 'T', 'slug' => 't', 'plan' => 'starter', 'status' => 'active']);
        app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId($this->tenant->id);
        $this->whA = Warehouse::create(['tenant_id' => $this->tenant->id, 'name' => 'A', 'code' => 'WH-A', 'is_default' => true]);
        $this->whB = Warehouse::create(['tenant_id' => $this->tenant->id, 'name' => 'B', 'code' => 'WH-B']);
    }

    private function user(string $role): User
    {
        $u = User::create(['name' => $role, 'email' => $role . '@t.com', 'password' => Hash::make('x'), 'tenant_id' => $this->tenant->id]);
        $u->assignRole($role);

        return $u;
    }

    private function assign(User $u, Warehouse ...$whs): void
    {
        foreach ($whs as $w) {
            DB::table('user_warehouses')->insert([
                'id' => (string) Str::uuid(), 'user_id' => $u->id, 'warehouse_id' => $w->id,
                'tenant_id' => $this->tenant->id, 'role' => 'operator', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    #[Test]
    public function managers_are_never_restricted_even_with_an_assignment(): void
    {
        $manager = $this->user('manager');
        $this->assign($manager, $this->whA);

        $this->assertNull($manager->accessibleWarehouseIds());
        $this->assertNull(WarehouseScope::resolve($manager, null));
    }

    #[Test]
    public function an_operator_without_assignment_is_unrestricted(): void
    {
        $this->assertNull($this->user('member')->accessibleWarehouseIds());
    }

    #[Test]
    public function an_operator_with_assignment_is_restricted_to_it(): void
    {
        $op = $this->user('member');
        $this->assign($op, $this->whA);

        $this->assertEqualsCanonicalizing([$this->whA->id], $op->accessibleWarehouseIds());
    }

    #[Test]
    public function resolve_intersects_the_request_with_the_restriction(): void
    {
        $op = $this->user('member');
        $this->assign($op, $this->whA);

        // no request → the whole allowed set
        $this->assertEqualsCanonicalizing([$this->whA->id], WarehouseScope::resolve($op, null));
        // requesting an allowed site → that site
        $this->assertSame([$this->whA->id], WarehouseScope::resolve($op, $this->whA->id));
        // requesting a NOT-allowed site → deny all (empty set, never a leak)
        $this->assertSame([], WarehouseScope::resolve($op, $this->whB->id));
    }

    #[Test]
    public function resolve_honours_an_optional_filter_for_unrestricted_users(): void
    {
        $manager = $this->user('manager');

        $this->assertSame([$this->whB->id], WarehouseScope::resolve($manager, $this->whB->id));
        $this->assertNull(WarehouseScope::resolve($manager, null));
    }
}
