<?php

namespace Tests\Feature\Rbac;

use App\Models\User;
use App\Modules\Customers\Models\Customer;
use App\Modules\Delivery\Models\Delivery;
use App\Modules\Suppliers\Models\Supplier;
use App\Modules\Tenants\Models\Tenant;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Audit RBAC P2 — les Policies (2ᵉ ligne de défense) tranchent correctement, indépendamment du
 * middleware de route : un `viewer` est refusé, `admin`/`manager` sont autorisés.
 */
class ModulePolicyTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function policies_authorize_writes_by_role_permission(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $tenant = Tenant::create([
            'name' => 'B', 'slug' => 'b-'.substr(md5(uniqid('', true)), 0, 8),
            'plan' => 'starter', 'status' => 'active', 'subscription_status' => 'trialing',
        ]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        $viewer  = User::factory()->create(['tenant_id' => $tenant->id]);  $viewer->assignRole('viewer');
        $manager = User::factory()->create(['tenant_id' => $tenant->id]);  $manager->assignRole('manager');
        $admin   = User::factory()->create(['tenant_id' => $tenant->id]);  $admin->assignRole('admin');

        // Admin & manager : autorisés sur les écritures.
        $this->assertTrue(Gate::forUser($admin)->allows('create', Supplier::class));
        $this->assertTrue(Gate::forUser($manager)->allows('update', Customer::class));
        $this->assertTrue(Gate::forUser($admin)->allows('create', Delivery::class));

        // Viewer (lecture seule) : refusé sur toutes les écritures.
        $this->assertFalse(Gate::forUser($viewer)->allows('create', Supplier::class));
        $this->assertFalse(Gate::forUser($viewer)->allows('update', Customer::class));
        $this->assertFalse(Gate::forUser($viewer)->allows('delete', Supplier::class));
        $this->assertFalse(Gate::forUser($viewer)->allows('create', Delivery::class));
    }
}
