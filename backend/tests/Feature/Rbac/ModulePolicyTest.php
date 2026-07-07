<?php

namespace Tests\Feature\Rbac;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Customers\Models\Customer;
use App\Modules\Delivery\Models\Delivery;
use App\Modules\ImportExport\Models\ImportSession;
use App\Modules\Orders\Models\Order;
use App\Modules\Payments\Models\Payment;
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

        // Admin & manager : autorisés sur les écritures (tous modules gardés par une policy).
        $this->assertTrue(Gate::forUser($admin)->allows('create', Supplier::class));
        $this->assertTrue(Gate::forUser($manager)->allows('update', Customer::class));
        $this->assertTrue(Gate::forUser($admin)->allows('create', Delivery::class));
        $this->assertTrue(Gate::forUser($admin)->allows('create', Product::class));
        $this->assertTrue(Gate::forUser($manager)->allows('delete', Product::class));
        $this->assertTrue(Gate::forUser($manager)->allows('create', Order::class));
        $this->assertTrue(Gate::forUser($admin)->allows('create', Payment::class));
        $this->assertTrue(Gate::forUser($admin)->allows('delete', Payment::class));
        $this->assertTrue(Gate::forUser($manager)->allows('create', ImportSession::class));
        $this->assertTrue(Gate::forUser($admin)->allows('update', ImportSession::class));

        // Viewer (lecture seule) : refusé sur toutes les écritures.
        $this->assertFalse(Gate::forUser($viewer)->allows('create', Supplier::class));
        $this->assertFalse(Gate::forUser($viewer)->allows('update', Customer::class));
        $this->assertFalse(Gate::forUser($viewer)->allows('delete', Supplier::class));
        $this->assertFalse(Gate::forUser($viewer)->allows('create', Delivery::class));
        $this->assertFalse(Gate::forUser($viewer)->allows('create', Product::class));
        $this->assertFalse(Gate::forUser($viewer)->allows('create', Order::class));
        $this->assertFalse(Gate::forUser($viewer)->allows('create', Payment::class));
        $this->assertFalse(Gate::forUser($viewer)->allows('update', ImportSession::class));
    }

    /**
     * `ProductPolicy` MIROIR le groupe de routes catalogue (OR sur toutes les permissions d'écriture) :
     * un rôle custom porteur d'une SEULE permission d'écriture produit passe TOUTES les écritures —
     * exactement comme aujourd'hui côté route. La policy ne durcit donc pas l'accès existant.
     */
    #[Test]
    public function product_policy_mirrors_the_coarse_catalog_route_group(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $tenant = Tenant::create([
            'name' => 'C', 'slug' => 'c-'.substr(md5(uniqid('', true)), 0, 8),
            'plan' => 'starter', 'status' => 'active', 'subscription_status' => 'trialing',
        ]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        // Rôle faible + une seule permission d'écriture produit (ex. `products.update`).
        $editor = User::factory()->create(['tenant_id' => $tenant->id]);
        $editor->assignRole('viewer');
        $editor->givePermissionTo('products.update');

        $this->assertTrue(Gate::forUser($editor)->allows('create', Product::class));
        $this->assertTrue(Gate::forUser($editor)->allows('update', Product::class));
        $this->assertTrue(Gate::forUser($editor)->allows('delete', Product::class));
    }

    /**
     * Anti-durcissement pour les policies *per-action* (Order/Payment/ImportSession).
     *
     * Les cas admin/manager passent par `hasAnyRole()` et n'exercent JAMAIS la 2ᵉ disjonction
     * `can("<module>.<action>")` de `ModulePolicy::allows`. Un décalage de slug/action (ex.
     * `$module = 'order'` au singulier) durcirait alors l'accès — la policy exigerait une permission
     * inexistante — SANS faire échouer les tests admin/manager ni le cas viewer. On vérifie donc
     * qu'un rôle custom portant EXACTEMENT la permission granulaire acceptée par la route est autorisé.
     */
    #[Test]
    public function per_action_policies_grant_on_the_granular_route_permission(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $tenant = Tenant::create([
            'name' => 'D', 'slug' => 'd-'.substr(md5(uniqid('', true)), 0, 8),
            'plan' => 'starter', 'status' => 'active', 'subscription_status' => 'trialing',
        ]);
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);

        // Orders : rôle faible + exactement `orders.create` (le nom que la route POST /orders accepte).
        $o = User::factory()->create(['tenant_id' => $tenant->id]);
        $o->assignRole('viewer');
        $o->givePermissionTo('orders.create');
        $this->assertTrue(Gate::forUser($o)->allows('create', Order::class));

        // Payments : `payments.create` (POST) + `payments.delete` (DELETE).
        $p = User::factory()->create(['tenant_id' => $tenant->id]);
        $p->assignRole('viewer');
        $p->givePermissionTo(['payments.create', 'payments.delete']);
        $this->assertTrue(Gate::forUser($p)->allows('create', Payment::class));
        $this->assertTrue(Gate::forUser($p)->allows('delete', Payment::class));

        // ImportSession : `import_export.create` (upload) + `import_export.update` (mapping/cancel).
        $i = User::factory()->create(['tenant_id' => $tenant->id]);
        $i->assignRole('viewer');
        $i->givePermissionTo(['import_export.create', 'import_export.update']);
        $this->assertTrue(Gate::forUser($i)->allows('create', ImportSession::class));
        $this->assertTrue(Gate::forUser($i)->allows('update', ImportSession::class));
    }
}
