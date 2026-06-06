<?php

namespace App\Modules\Payments\Tests\Integration;

use App\Models\User;
use App\Modules\Billing\Models\Plan;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Services\StockService;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Services\OrderService;
use App\Modules\Payments\Models\Payment;
use App\Modules\Tenants\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PaymentApiTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private string $token;
    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        // Sprint 11: admin role required for void (destroy) operations
        Role::firstOrCreate(['name' => 'admin',  'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'manager','guard_name' => 'web']);
        Plan::firstOrCreate(['code' => 'starter'], ['name' => 'Starter', 'price_monthly_cents' => 0, 'price_yearly_cents' => 0, 'currency' => 'XOF', 'trial_days' => 14, 'is_active' => true, 'is_public' => true, 'sort_order' => 1]);
        $this->tenant = Tenant::create(['name' => 'Boutique Test', 'slug' => 'boutique-test', 'plan' => 'starter', 'status' => 'active', 'settings' => []]);
        $this->user   = User::create(['name' => 'Manager', 'email' => 'manager@test.com', 'password' => Hash::make('Secret123!'), 'tenant_id' => $this->tenant->id]);
        $this->user->assignTenantRole('admin'); // admin can void payments (Sprint 11 role guard)
        $this->token  = $this->user->createToken('api')->plainTextToken;

        $product = Product::create(['tenant_id' => $this->tenant->id, 'sku' => 'TST-001', 'name' => 'Produit', 'price_amount' => 20000, 'price_currency' => 'EUR', 'status' => 'active']);
        $stock   = $this->app->make(StockService::class)->findOrCreate($this->tenant->id, $product->id, null);
        $this->app->make(StockService::class)->moveIn($stock, 50);
        $this->order = $this->app->make(OrderService::class)->create(['items' => [['product_id' => $product->id, 'quantity' => 2]]], $this->tenant->id, $this->user->id);
        // total = 40 000 cents
    }

    private function auth(): array { return ['Authorization' => "Bearer {$this->token}"]; }

    #[Test]
    public function it_records_a_payment_for_an_order(): void
    {
        $res = $this->postJson('/api/payments', [
            'order_id'     => $this->order->id,
            'amount_cents' => 40000,
            'currency'     => 'EUR',
            'method'       => 'cash',
        ], $this->auth());

        $res->assertCreated()
            ->assertJsonPath('data.amount_cents', 40000)
            ->assertJsonPath('data.method', 'cash')
            ->assertJsonPath('is_fully_paid', true)
            ->assertJsonPath('balance', 40000);

        $this->assertDatabaseHas('payments', ['order_id' => $this->order->id]);
    }

    #[Test]
    public function it_validates_required_fields(): void
    {
        $res = $this->postJson('/api/payments', ['method' => 'cash'], $this->auth());
        $res->assertUnprocessable()->assertJsonValidationErrors(['amount_cents', 'currency']);
    }

    #[Test]
    public function it_validates_method_enum(): void
    {
        $res = $this->postJson('/api/payments', [
            'amount_cents' => 1000, 'currency' => 'EUR', 'method' => 'bitcoin',
        ], $this->auth());
        $res->assertUnprocessable()->assertJsonValidationErrors(['method']);
    }

    #[Test]
    public function it_returns_partial_payment_balance(): void
    {
        $this->postJson('/api/payments', ['order_id' => $this->order->id, 'amount_cents' => 10000, 'currency' => 'EUR', 'method' => 'cash'], $this->auth());
        $res = $this->postJson('/api/payments', ['order_id' => $this->order->id, 'amount_cents' => 15000, 'currency' => 'EUR', 'method' => 'card'], $this->auth());

        $res->assertCreated()
            ->assertJsonPath('balance', 25000)
            ->assertJsonPath('is_fully_paid', false);
    }

    #[Test]
    public function it_shows_a_payment(): void
    {
        $res  = $this->postJson('/api/payments', ['amount_cents' => 5000, 'currency' => 'EUR', 'method' => 'cash'], $this->auth());
        $id   = $res->json('data.id');

        $this->getJson("/api/payments/{$id}", $this->auth())
            ->assertOk()
            ->assertJsonPath('data.id', $id);
    }

    #[Test]
    public function it_lists_payments_for_an_order_via_nested_route(): void
    {
        $this->postJson('/api/payments', ['order_id' => $this->order->id, 'amount_cents' => 20000, 'currency' => 'EUR', 'method' => 'cash'], $this->auth());
        $this->postJson('/api/payments', ['order_id' => $this->order->id, 'amount_cents' => 20000, 'currency' => 'EUR', 'method' => 'card'], $this->auth());

        $res = $this->getJson("/api/orders/{$this->order->id}/payments", $this->auth());

        $res->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('balance', 40000)
            ->assertJsonPath('is_fully_paid', true);
    }

    #[Test]
    public function it_voids_a_payment(): void
    {
        $res = $this->postJson('/api/payments', ['amount_cents' => 5000, 'currency' => 'EUR', 'method' => 'cash'], $this->auth());
        $id  = $res->json('data.id');

        $this->deleteJson("/api/payments/{$id}", [], $this->auth())->assertNoContent();
        $this->assertSoftDeleted('payments', ['id' => $id]);
    }

    #[Test]
    public function it_requires_auth(): void
    {
        $this->getJson('/api/payments')->assertUnauthorized();
        $this->postJson('/api/payments', [])->assertUnauthorized();
    }

    #[Test]
    public function it_cannot_access_another_tenants_payment(): void
    {
        $otherTenant = Tenant::create(['name' => 'Other', 'slug' => 'other', 'plan' => 'starter', 'status' => 'active']);
        $payment = Payment::create(['tenant_id' => $otherTenant->id, 'amount_cents' => 1000, 'currency' => 'EUR', 'method' => 'cash', 'paid_at' => now()]);

        $this->getJson("/api/payments/{$payment->id}", $this->auth())->assertNotFound();
    }

    #[Test]
    public function it_lists_payments_with_method_filter(): void
    {
        $this->postJson('/api/payments', ['amount_cents' => 1000, 'currency' => 'EUR', 'method' => 'cash'], $this->auth());
        $this->postJson('/api/payments', ['amount_cents' => 2000, 'currency' => 'EUR', 'method' => 'card'], $this->auth());

        $res = $this->getJson('/api/payments?method=cash', $this->auth());
        $res->assertOk()->assertJsonCount(1, 'data');
    }

    #[Test]
    public function it_returns_existing_payment_on_idempotent_replay(): void
    {
        $key     = 'test-idem-key-abc123';
        $headers = array_merge($this->auth(), ['X-Idempotency-Key' => $key]);

        $payload = ['amount_cents' => 5000, 'currency' => 'EUR', 'method' => 'cash'];

        $first = $this->postJson('/api/payments', $payload, $headers);
        $first->assertCreated();
        $firstId = $first->json('data.id');

        $second = $this->postJson('/api/payments', $payload, $headers);
        $second->assertOk()
               ->assertJsonPath('data.id', $firstId);

        $this->assertDatabaseCount('payments', 1);
    }

    #[Test]
    public function it_stores_idempotency_key_on_first_request(): void
    {
        $key     = 'test-idem-key-store';
        $headers = array_merge($this->auth(), ['X-Idempotency-Key' => $key]);

        $res = $this->postJson('/api/payments', ['amount_cents' => 3000, 'currency' => 'EUR', 'method' => 'cash'], $headers);
        $res->assertCreated();

        $this->assertDatabaseHas('payments', [
            'id'              => $res->json('data.id'),
            'idempotency_key' => $key,
            'tenant_id'       => $this->tenant->id,
        ]);
    }

}
