<?php
namespace App\Modules\Orders\Tests\Integration;

use App\Models\User;
use App\Modules\Billing\Models\Plan;
use App\Modules\Orders\Models\Order;
use App\Modules\Tenants\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OrdersRbacTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $admin;
    private User $viewer;
    private Order $order;
    private string $adminToken;
    private string $viewerToken;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['admin', 'manager', 'member', 'viewer', 'cashier', 'agent'] as $r) {
            Role::firstOrCreate(['name' => $r, 'guard_name' => 'web']);
        }
        Plan::firstOrCreate(['code' => 'starter'], ['name' => 'Starter', 'price_monthly_cents' => 0, 'price_yearly_cents' => 0, 'currency' => 'XOF', 'trial_days' => 14, 'is_active' => true, 'is_public' => true, 'sort_order' => 1]);
        $this->tenant = Tenant::create(['name' => 'T', 'slug' => 'ord-rbac', 'plan' => 'starter', 'status' => 'active', 'settings' => []]);
        $this->admin  = User::create(['name' => 'A', 'email' => 'a@ord-rbac.sn', 'password' => bcrypt('x'), 'tenant_id' => $this->tenant->id]);
        $this->admin->assignTenantRole('admin');
        $this->adminToken  = $this->admin->createToken('api')->plainTextToken;
        $this->viewer = User::create(['name' => 'V', 'email' => 'v@ord-rbac.sn', 'password' => bcrypt('x'), 'tenant_id' => $this->tenant->id]);
        $this->viewer->assignTenantRole('viewer');
        $this->viewerToken = $this->viewer->createToken('api')->plainTextToken;
        $this->order = Order::withoutTenantScope()->create(['tenant_id' => $this->tenant->id, 'number' => 'ORD-001', 'status' => 'draft', 'currency' => 'XOF', 'subtotal_cents' => 10000, 'tax_cents' => 0, 'total_cents' => 10000, 'discount_cents' => 0]);
    }

    #[Test]
    public function viewer_cannot_confirm_order(): void
    {
        $this->withToken($this->viewerToken)
            ->postJson("/api/orders/{$this->order->id}/confirm")
            ->assertStatus(403);
    }

    #[Test]
    public function viewer_cannot_cancel_order(): void
    {
        $this->withToken($this->viewerToken)
            ->postJson("/api/orders/{$this->order->id}/cancel")
            ->assertStatus(403);
    }

    #[Test]
    public function viewer_cannot_approve_return(): void
    {
        $fakeId = '00000000-0000-0000-0000-000000000000';
        $this->withToken($this->viewerToken)
            ->postJson("/api/orders/returns/{$fakeId}/approve")
            ->assertStatus(403);
    }

    #[Test]
    public function admin_can_list_orders(): void
    {
        $this->withToken($this->adminToken)->getJson('/api/orders/')->assertOk();
    }

    #[Test]
    public function viewer_can_list_orders(): void
    {
        $this->withToken($this->viewerToken)->getJson('/api/orders/')->assertOk();
    }

    #[Test]
    public function viewer_cannot_approve_return_restock(): void
    {
        $fakeId = '00000000-0000-0000-0000-000000000001';
        $this->withToken($this->viewerToken)
            ->postJson("/api/orders/returns/{$fakeId}/restock")
            ->assertStatus(403);
    }
}
