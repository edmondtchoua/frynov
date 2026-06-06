<?php

namespace App\Modules\Auth\Tests\Integration;

use App\Models\User;
use App\Modules\Auth\Services\TenantRoleService;
use App\Modules\Billing\Models\Plan;
use App\Modules\Catalog\Models\Product;
use App\Modules\Customers\Models\Customer;
use App\Modules\Inventory\Models\Stock;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Orders\Models\Order;
use App\Modules\Tenants\Models\Tenant;
use Database\Seeders\ErpModulesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * RBAC Phase B2.2 — sensitive write routes are gated by
 * `role_or_permission:manager|admin|<granular-perm>`.
 *
 * Contract verified here:
 *   1. A CUSTOM role granted a granular write permission (e.g. products.create)
 *      PASSES the gate on the matching route.
 *   2. A `member` (which holds module.action perms like catalog.create / customers.create
 *      but NOT the granular products.create / customers.delete) is STILL denied — base-role
 *      behaviour is preserved.
 *   3. The base `admin` role keeps full access by role, with no permission grant.
 *
 * NOTE: each test issues exactly ONE authenticated HTTP request. The Sanctum guard
 * caches the first user it resolves within a single test, so mixing two tokens in one
 * test would silently reuse the first user (see TenantRoleTest for the same caveat).
 */
class PermissionEnforcementTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ErpModulesSeeder::class);
        Plan::firstOrCreate(['code' => 'starter'], ['name' => 'Starter', 'price_monthly_cents' => 0, 'price_yearly_cents' => 0, 'currency' => 'XOF', 'trial_days' => 14, 'is_active' => true, 'is_public' => true, 'sort_order' => 1]);

        $this->tenant = Tenant::create(['name' => 'T', 'slug' => 'perm-enf', 'plan' => 'starter', 'status' => 'active', 'settings' => []]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($this->tenant->id);
    }

    /** Token for a user holding a base tenant role. */
    private function tokenForRole(string $role): string
    {
        $user = User::create(['name' => $role, 'email' => "{$role}@perm-enf.sn", 'password' => bcrypt('x'), 'tenant_id' => $this->tenant->id]);
        $user->assignTenantRole($role);

        return $user->createToken('api')->plainTextToken;
    }

    /** Token for a user holding a freshly-created custom role with the given permissions. */
    private function tokenForCustomRole(string $name, array $permissions): string
    {
        $role = app(TenantRoleService::class)->create($this->tenant, $name, $permissions);

        $user = User::create(['name' => $name, 'email' => str()->slug($name) . '@perm-enf.sn', 'password' => bcrypt('x'), 'tenant_id' => $this->tenant->id]);
        $user->assignTenantRole($role->name);

        return $user->createToken('api')->plainTextToken;
    }

    // ── Catalog: products.create ────────────────────────────────────────────────

    #[Test]
    public function member_cannot_create_a_product(): void
    {
        $this->withToken($this->tokenForRole('member'))
            ->postJson('/api/catalog/products', ['name' => 'X', 'price_amount' => 1000, 'price_currency' => 'XOF'])
            ->assertStatus(403);
    }

    #[Test]
    public function a_custom_role_with_products_create_can_create_a_product(): void
    {
        $token = $this->tokenForCustomRole('Catalogue', ['products.create', 'products.update', 'products.delete', 'products.archive']);

        $this->withToken($token)
            ->postJson('/api/catalog/products', ['name' => 'Boubou', 'price_amount' => 25000, 'price_currency' => 'XOF'])
            ->assertCreated();
    }

    #[Test]
    public function the_admin_role_still_creates_products_without_any_permission_grant(): void
    {
        $this->withToken($this->tokenForRole('admin'))
            ->postJson('/api/catalog/products', ['name' => 'Admin Prod', 'price_amount' => 5000, 'price_currency' => 'XOF'])
            ->assertCreated();
    }

    // ── Customers: customers.delete ─────────────────────────────────────────────

    #[Test]
    public function member_cannot_delete_a_customer(): void
    {
        // member holds customers.create/update/view, NOT customers.delete.
        $customer = Customer::create(['tenant_id' => $this->tenant->id, 'name' => 'Client', 'phone' => '+221770000000']);

        $this->withToken($this->tokenForRole('member'))
            ->deleteJson("/api/customers/{$customer->id}")
            ->assertStatus(403);
    }

    #[Test]
    public function a_custom_role_with_customers_delete_can_delete_a_customer(): void
    {
        $customer = Customer::create(['tenant_id' => $this->tenant->id, 'name' => 'Client', 'phone' => '+221770000001']);

        $this->withToken($this->tokenForCustomRole('SAV', ['customers.delete']))
            ->deleteJson("/api/customers/{$customer->id}")
            ->assertSuccessful();
    }

    // ── Inventory: inventory.adjust ─────────────────────────────────────────────

    #[Test]
    public function member_cannot_adjust_stock(): void
    {
        // member holds inventory.create/update/view, NOT inventory.adjust.
        $product = $this->productWithStock();

        $this->withToken($this->tokenForRole('member'))
            ->postJson("/api/inventory/stock/{$product->id}/adjust", ['quantity' => 5, 'note' => 'x'])
            ->assertStatus(403);
    }

    #[Test]
    public function a_custom_role_with_inventory_adjust_passes_the_stock_gate(): void
    {
        $product = $this->productWithStock();

        $resp = $this->withToken($this->tokenForCustomRole('Stock', ['inventory.adjust']))
            ->postJson("/api/inventory/stock/{$product->id}/adjust", ['quantity' => 5, 'note' => 'ok']);

        $this->assertNotSame(403, $resp->status(), 'inventory.adjust must pass the stock write gate');
    }

    // ── Orders: orders.manage ───────────────────────────────────────────────────

    #[Test]
    public function member_cannot_confirm_an_order(): void
    {
        // member holds orders.create/update/view, NOT orders.manage.
        $order = $this->draftOrder('ORD-ENF-1');

        $this->withToken($this->tokenForRole('member'))
            ->postJson("/api/orders/{$order->id}/confirm")
            ->assertStatus(403);
    }

    #[Test]
    public function a_custom_role_with_orders_manage_passes_the_order_gate(): void
    {
        $order = $this->draftOrder('ORD-ENF-2');

        $resp = $this->withToken($this->tokenForCustomRole('Validation', ['orders.manage']))
            ->postJson("/api/orders/{$order->id}/confirm");

        $this->assertNotSame(403, $resp->status(), 'orders.manage must pass the order-lifecycle gate');
    }

    // ── fixtures ────────────────────────────────────────────────────────────────

    private function productWithStock(): Product
    {
        $wh = Warehouse::create(['tenant_id' => $this->tenant->id, 'name' => 'Main', 'code' => 'WH-ENF-' . uniqid(), 'is_default' => true]);
        $product = Product::withoutTenantScope()->create(['tenant_id' => $this->tenant->id, 'sku' => 'ENF-' . uniqid(), 'name' => 'P', 'price_amount' => 1000, 'price_currency' => 'XOF', 'status' => 'active', 'has_variants' => false]);
        Stock::create(['tenant_id' => $this->tenant->id, 'warehouse_id' => $wh->id, 'product_id' => $product->id, 'quantity' => 10, 'reserved_quantity' => 0, 'low_stock_threshold' => 2, 'unit_cost_cents' => 1000, 'total_value_cents' => 10000]);

        return $product;
    }

    private function draftOrder(string $number): Order
    {
        return Order::withoutTenantScope()->create(['tenant_id' => $this->tenant->id, 'number' => $number, 'status' => 'draft', 'currency' => 'XOF', 'subtotal_cents' => 10000, 'tax_cents' => 0, 'total_cents' => 10000, 'discount_cents' => 0]);
    }
}
