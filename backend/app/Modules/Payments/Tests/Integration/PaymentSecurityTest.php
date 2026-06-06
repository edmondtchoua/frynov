<?php
namespace App\Modules\Payments\Tests\Integration;

use App\Models\User;
use App\Modules\Billing\Models\Plan;
use App\Modules\Orders\Models\Order;
use App\Modules\Payments\Models\Payment;
use App\Modules\Tenants\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PaymentSecurityTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $admin;
    private User $viewer;
    private string $adminToken;
    private string $viewerToken;
    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'viewer', 'guard_name' => 'web']);
        Plan::firstOrCreate(['code' => 'starter'], ['name' => 'Starter', 'price_monthly_cents' => 0, 'price_yearly_cents' => 0, 'currency' => 'XOF', 'trial_days' => 14, 'is_active' => true, 'is_public' => true, 'sort_order' => 1]);
        $this->tenant  = Tenant::create(['name' => 'T', 'slug' => 'pay-sec', 'plan' => 'starter', 'status' => 'active', 'settings' => []]);
        $this->admin   = User::create(['name' => 'A', 'email' => 'a@pay-sec.sn', 'password' => bcrypt('x'), 'tenant_id' => $this->tenant->id]);
        $this->admin->assignTenantRole('admin');
        $this->viewer  = User::create(['name' => 'V', 'email' => 'v@pay-sec.sn', 'password' => bcrypt('x'), 'tenant_id' => $this->tenant->id]);
        $this->viewer->assignTenantRole('viewer');
        $this->adminToken  = $this->admin->createToken('api')->plainTextToken;
        $this->viewerToken = $this->viewer->createToken('api')->plainTextToken;
        $this->order = Order::withoutTenantScope()->create(['tenant_id' => $this->tenant->id, 'number' => 'ORD-SEC-001', 'status' => 'confirmed', 'currency' => 'XOF', 'subtotal_cents' => 50000, 'tax_cents' => 0, 'total_cents' => 50000, 'discount_cents' => 0, 'total_amount' => 50000]);
    }

    #[Test]
    public function payment_amount_cannot_exceed_order_total(): void
    {
        $this->withToken($this->adminToken)
            ->postJson('/api/payments', [
                'order_id'     => $this->order->id,
                'amount_cents' => 999999, // way over the 50000 order total
                'currency'     => 'XOF',
                'method'       => 'cash',
            ])
            ->assertStatus(422);
    }

    #[Test]
    public function payment_within_order_total_is_accepted(): void
    {
        $this->withToken($this->adminToken)
            ->postJson('/api/payments', [
                'order_id'     => $this->order->id,
                'amount_cents' => 50000,
                'currency'     => 'XOF',
                'method'       => 'cash',
            ])
            ->assertStatus(201);
    }

    #[Test]
    public function idempotency_key_prevents_double_payment(): void
    {
        $headers = ['X-Idempotency-Key' => 'unique-key-123'];
        $payload = ['amount_cents' => 10000, 'currency' => 'XOF', 'method' => 'cash'];

        $first  = $this->withToken($this->adminToken)->postJson('/api/payments', $payload, $headers)->assertStatus(201);
        $second = $this->withToken($this->adminToken)->postJson('/api/payments', $payload, $headers)->assertStatus(200);

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertDatabaseCount('payments', 1);
    }

    #[Test]
    public function viewer_cannot_void_payment(): void
    {
        $payment = Payment::create(['tenant_id' => $this->tenant->id, 'amount_cents' => 1000, 'currency' => 'XOF', 'method' => 'cash', 'paid_at' => now(), 'performed_by' => $this->admin->id]);

        $this->withToken($this->viewerToken)
            ->deleteJson("/api/payments/{$payment->id}")
            ->assertStatus(403);
    }

    #[Test]
    public function admin_can_void_payment(): void
    {
        $payment = Payment::create(['tenant_id' => $this->tenant->id, 'amount_cents' => 1000, 'currency' => 'XOF', 'method' => 'cash', 'paid_at' => now(), 'performed_by' => $this->admin->id]);

        $this->withToken($this->adminToken)
            ->deleteJson("/api/payments/{$payment->id}")
            ->assertSuccessful(); // 200 or 204
    }
}
