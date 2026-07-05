<?php
namespace App\Modules\Inventory\Tests\Integration;

use App\Models\User;
use App\Modules\Billing\Models\Plan;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\Stock;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Tenants\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InventorySecurityTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private User $adminA;
    private User $adminB;
    private Product $productA;
    private Product $productB;
    private Warehouse $whA;
    private Warehouse $whB;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Plan::firstOrCreate(['code' => 'starter'], ['name' => 'Starter', 'price_monthly_cents' => 0, 'price_yearly_cents' => 0, 'currency' => 'XOF', 'trial_days' => 14, 'is_active' => true, 'is_public' => true, 'sort_order' => 1]);

        $this->tenantA = Tenant::create(['name' => 'A', 'slug' => 'tenant-a-sec', 'plan' => 'starter', 'status' => 'active', 'settings' => []]);
        $this->tenantB = Tenant::create(['name' => 'B', 'slug' => 'tenant-b-sec', 'plan' => 'starter', 'status' => 'active', 'settings' => []]);

        $this->adminA = User::create(['name' => 'A', 'email' => 'a@sec-a.sn', 'password' => bcrypt('x'), 'tenant_id' => $this->tenantA->id]);
        $this->adminA->assignTenantRole('admin');

        $this->adminB = User::create(['name' => 'B', 'email' => 'b@sec-b.sn', 'password' => bcrypt('x'), 'tenant_id' => $this->tenantB->id]);
        $this->adminB->assignTenantRole('admin');

        $this->whA = Warehouse::create(['tenant_id' => $this->tenantA->id, 'name' => 'WH-A', 'code' => 'WH-A-SEC', 'is_default' => true]);
        $this->whB = Warehouse::create(['tenant_id' => $this->tenantB->id, 'name' => 'WH-B', 'code' => 'WH-B-SEC', 'is_default' => true]);

        $this->productA = Product::withoutTenantScope()->create(['tenant_id' => $this->tenantA->id, 'sku' => 'SEC-A-001', 'name' => 'Product A', 'price_amount' => 1000, 'price_currency' => 'XOF', 'status' => 'active', 'has_variants' => false]);
        $this->productB = Product::withoutTenantScope()->create(['tenant_id' => $this->tenantB->id, 'sku' => 'SEC-B-001', 'name' => 'Product B', 'price_amount' => 2000, 'price_currency' => 'XOF', 'status' => 'active', 'has_variants' => false]);
    }

    #[Test]
    public function tenant_a_cannot_view_stock_of_tenant_b_product(): void
    {
        $tokenA = $this->adminA->createToken('api')->plainTextToken;

        // tenantA tries to access a product owned by tenantB
        $this->withToken($tokenA)
            ->getJson("/api/inventory/stock/{$this->productB->id}")
            ->assertStatus(404); // 404 because product not found in tenantA scope
    }

    #[Test]
    public function batch_delivery_rejects_cross_tenant_product(): void
    {
        $tokenA = $this->adminA->createToken('api')->plainTextToken;

        // tenantA submits a delivery with tenantB's product UUID
        $this->withToken($tokenA)
            ->postJson('/api/inventory/deliveries', [
                'items' => [
                    ['product_id' => $this->productB->id, 'quantity' => 5],
                ],
            ])
            ->assertStatus(422); // validation must reject the cross-tenant product_id
    }

    #[Test]
    public function warehouse_summary_of_other_tenant_returns_404(): void
    {
        $tokenA = $this->adminA->createToken('api')->plainTextToken;

        // tenantA tries to access whB summary
        $this->withToken($tokenA)
            ->getJson("/api/inventory/warehouses/{$this->whB->id}/summary")
            ->assertStatus(404);
    }

    #[Test]
    public function viewer_cannot_adjust_stock(): void
    {
        Role::firstOrCreate(['name' => 'viewer', 'guard_name' => 'web']);
        $viewer = User::create(['name' => 'V', 'email' => 'v@sec-a.sn', 'password' => bcrypt('x'), 'tenant_id' => $this->tenantA->id]);
        $viewer->assignTenantRole('viewer');
        $token = $viewer->createToken('api')->plainTextToken;

        Stock::create(['tenant_id' => $this->tenantA->id, 'warehouse_id' => $this->whA->id, 'product_id' => $this->productA->id, 'quantity' => 10, 'reserved_quantity' => 0, 'low_stock_threshold' => 2, 'unit_cost_cents' => 1000, 'total_value_cents' => 10000]);

        $this->withToken($token)
            ->postJson("/api/inventory/stock/{$this->productA->id}/adjust", ['quantity' => 5, 'note' => 'Test adjustment'])
            ->assertStatus(403);
    }
}
