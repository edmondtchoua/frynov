<?php

namespace App\Modules\Inventory\Tests\Integration;

use App\Models\User;
use App\Modules\Billing\Models\Plan;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Orders\Models\Order;
use App\Modules\Tenants\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * End-to-end proof that per-agency access scoping (Sprint 20) holds through the HTTP
 * layer: a warehouse-restricted operator must never see another site's data.
 */
class WarehouseAccessScopingTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $manager;
    private User $operator;
    private string $managerToken;
    private string $operatorToken;
    private Warehouse $whA;
    private Warehouse $whB;
    private Order $orderA;
    private Order $orderB;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['admin', 'manager', 'member', 'viewer', 'cashier', 'agent'] as $r) {
            Role::firstOrCreate(['name' => $r, 'guard_name' => 'web']);
        }
        Plan::firstOrCreate(['code' => 'starter'], ['name' => 'Starter', 'price_monthly_cents' => 0, 'price_yearly_cents' => 0, 'currency' => 'XOF', 'trial_days' => 14, 'is_active' => true, 'is_public' => true, 'sort_order' => 1]);

        $this->tenant = Tenant::create(['name' => 'T', 'slug' => 'wh-scope', 'plan' => 'starter', 'status' => 'active', 'settings' => []]);

        $this->manager = User::create(['name' => 'M', 'email' => 'm@wh-scope.sn', 'password' => bcrypt('x'), 'tenant_id' => $this->tenant->id]);
        $this->manager->assignTenantRole('manager');
        $this->managerToken = $this->manager->createToken('api')->plainTextToken;

        $this->operator = User::create(['name' => 'O', 'email' => 'o@wh-scope.sn', 'password' => bcrypt('x'), 'tenant_id' => $this->tenant->id]);
        $this->operator->assignTenantRole('member');
        $this->operatorToken = $this->operator->createToken('api')->plainTextToken;

        $this->whA = Warehouse::create(['tenant_id' => $this->tenant->id, 'name' => 'Site A', 'code' => 'WH-A', 'is_default' => true]);
        $this->whB = Warehouse::create(['tenant_id' => $this->tenant->id, 'name' => 'Site B', 'code' => 'WH-B']);

        $this->orderA = $this->order('ORD-A', $this->whA->id);
        $this->orderB = $this->order('ORD-B', $this->whB->id);
    }

    private function order(string $number, string $warehouseId): Order
    {
        return Order::withoutTenantScope()->create([
            'tenant_id' => $this->tenant->id, 'number' => $number, 'status' => 'draft', 'currency' => 'XOF',
            'subtotal_cents' => 10000, 'tax_cents' => 0, 'total_cents' => 10000, 'discount_cents' => 0,
            'warehouse_id' => $warehouseId,
        ]);
    }

    /** @return array<int,string> the order ids returned by GET /api/orders */
    private function listOrderIds(string $token, string $query = ''): array
    {
        return collect($this->withToken($token)->getJson("/api/orders/{$query}")->assertOk()->json('data'))
            ->pluck('id')->all();
    }

    private function assignToWarehouse(User $user, Warehouse $wh): void
    {
        DB::table('user_warehouses')->insert([
            'id' => (string) \Illuminate\Support\Str::uuid(), 'user_id' => $user->id, 'warehouse_id' => $wh->id,
            'tenant_id' => $this->tenant->id, 'role' => 'operator', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    #[Test]
    public function a_manager_can_assign_an_operators_warehouses_via_the_endpoint(): void
    {
        $this->withToken($this->managerToken)
            ->putJson("/api/workspace/users/{$this->operator->id}/warehouses", ['warehouse_ids' => [$this->whA->id]])
            ->assertOk()
            ->assertJsonPath('data.warehouse_ids', [$this->whA->id]);

        $this->assertDatabaseHas('user_warehouses', ['user_id' => $this->operator->id, 'warehouse_id' => $this->whA->id]);
    }

    #[Test]
    public function a_restricted_operator_only_sees_their_site_orders(): void
    {
        $this->assignToWarehouse($this->operator, $this->whA);

        $ids = $this->listOrderIds($this->operatorToken);
        $this->assertContains($this->orderA->id, $ids);
        $this->assertNotContains($this->orderB->id, $ids, 'restricted operator must not see another site order');
    }

    #[Test]
    public function a_manager_sees_every_sites_orders(): void
    {
        $this->assignToWarehouse($this->manager, $this->whA);

        $ids = $this->listOrderIds($this->managerToken);
        $this->assertContains($this->orderA->id, $ids);
        $this->assertContains($this->orderB->id, $ids, 'managers are never warehouse-restricted');
    }

    #[Test]
    public function a_restricted_operator_requesting_a_forbidden_site_gets_nothing(): void
    {
        $this->assignToWarehouse($this->operator, $this->whA);

        // Explicitly asking for Site B (not assigned) must return an empty list, never a leak.
        $ids = $this->listOrderIds($this->operatorToken, "?warehouse_id={$this->whB->id}");
        $this->assertSame([], $ids);
    }

    #[Test]
    public function a_non_manager_cannot_assign_warehouses(): void
    {
        $this->withToken($this->operatorToken)
            ->putJson("/api/workspace/users/{$this->manager->id}/warehouses", ['warehouse_ids' => [$this->whA->id]])
            ->assertStatus(403);
    }
}
