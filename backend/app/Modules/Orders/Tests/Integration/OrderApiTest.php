<?php

namespace App\Modules\Orders\Tests\Integration;

use App\Models\User;
use App\Modules\Billing\Models\Plan;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Services\StockService;
use App\Modules\Orders\Models\Order;
use App\Modules\Tenants\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OrderApiTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private string $token;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin',   'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'viewer',  'guard_name' => 'web']);
        Plan::firstOrCreate(['code' => 'starter'], ['name' => 'Starter', 'price_monthly_cents' => 0, 'price_yearly_cents' => 0, 'currency' => 'XOF', 'trial_days' => 14, 'is_active' => true, 'is_public' => true, 'sort_order' => 1]);

        $this->tenant = Tenant::create([
            'name'     => 'Boutique Test',
            'slug'     => 'boutique-test',
            'plan'     => 'starter',
            'status'   => 'active',
            'settings' => [],
        ]);

        $this->user = User::create([
            'name'      => 'Manager',
            'email'     => 'manager@boutique-test.sn',
            'password'  => Hash::make('Secret123!'),
            'tenant_id' => $this->tenant->id,
        ]);
        $this->user->assignTenantRole('admin');

        $this->token = $this->user->createToken('api')->plainTextToken;

        $this->product = Product::create([
            'tenant_id'      => $this->tenant->id,
            'sku'            => 'VET-0001',
            'name'           => 'Boubou Sénégalais',
            'price_amount'   => 25000,
            'price_currency' => 'XOF',
            'status'         => 'active',
        ]);

        $stock = $this->app->make(StockService::class)
            ->findOrCreate($this->tenant->id, $this->product->id, null);
        $this->app->make(StockService::class)->moveIn($stock, 100);
    }

    private function auth(): array
    {
        return ['Authorization' => "Bearer {$this->token}"];
    }

    #[Test]
    public function it_returns_empty_order_list(): void
    {
        $response = $this->getJson('/api/orders', $this->auth());

        $response->assertOk()
            ->assertJsonPath('data', []);
    }

    #[Test]
    public function it_creates_an_order(): void
    {
        $response = $this->postJson('/api/orders', [
            'items' => [[
                'product_id' => $this->product->id,
                'quantity'   => 3,
            ]],
        ], $this->auth());

        $response->assertStatus(201)
            ->assertJsonPath('status', 'draft')
            ->assertJsonPath('total_amount', 75000)
            ->assertJsonCount(1, 'lines');
    }

    #[Test]
    public function it_rejects_order_with_no_items(): void
    {
        $response = $this->postJson('/api/orders', ['items' => []], $this->auth());

        $response->assertStatus(422);
    }

    #[Test]
    public function it_shows_an_order(): void
    {
        $created = $this->postJson('/api/orders', [
            'items' => [['product_id' => $this->product->id, 'quantity' => 1]],
        ], $this->auth())->json();

        $response = $this->getJson("/api/orders/{$created['id']}", $this->auth());

        $response->assertOk()
            ->assertJsonPath('id', $created['id']);
    }

    #[Test]
    public function it_confirms_an_order(): void
    {
        $order = $this->postJson('/api/orders', [
            'items' => [['product_id' => $this->product->id, 'quantity' => 5]],
        ], $this->auth())->json();

        $response = $this->postJson("/api/orders/{$order['id']}/confirm", [], $this->auth());

        $response->assertOk()
            ->assertJsonPath('status', 'confirmed');
    }

    #[Test]
    public function it_fulfills_a_confirmed_order(): void
    {
        $order = $this->postJson('/api/orders', [
            'items' => [['product_id' => $this->product->id, 'quantity' => 5]],
        ], $this->auth())->json();

        $this->postJson("/api/orders/{$order['id']}/confirm", [], $this->auth());
        $response = $this->postJson("/api/orders/{$order['id']}/fulfill", [], $this->auth());

        $response->assertOk()
            ->assertJsonPath('status', 'fulfilled');
    }

    #[Test]
    public function it_fulfills_an_order_consuming_all_available_stock(): void
    {
        // Regression: when stock is EXACTLY the order quantity, confirm reserves
        // everything (available → 0), and fulfill must still succeed by consuming
        // the reserved stock. Previously moveOut() ran before release() and threw
        // InsufficientStockException because available() was 0.
        $tightProduct = Product::create([
            'tenant_id' => $this->tenant->id, 'sku' => 'TIGHT-001',
            'name' => 'Stock serré', 'price_amount' => 1000, 'price_currency' => 'XOF',
            'status' => 'active',
        ]);
        $stock = $this->app->make(StockService::class)
            ->findOrCreate($this->tenant->id, $tightProduct->id, null);
        $this->app->make(StockService::class)->moveIn($stock, 5); // exactly 5

        $order = $this->postJson('/api/orders', [
            'items' => [['product_id' => $tightProduct->id, 'quantity' => 5]],
        ], $this->auth())->json();

        $this->postJson("/api/orders/{$order['id']}/confirm", [], $this->auth())->assertOk();
        $this->postJson("/api/orders/{$order['id']}/fulfill", [], $this->auth())
            ->assertOk()
            ->assertJsonPath('status', 'fulfilled');

        // Stock fully consumed: quantity 0, reserved 0
        $stock->refresh();
        $this->assertSame(0, $stock->quantity);
        $this->assertSame(0, $stock->reserved_quantity);
    }

    #[Test]
    public function it_cancels_a_draft_order(): void
    {
        $order = $this->postJson('/api/orders', [
            'items' => [['product_id' => $this->product->id, 'quantity' => 2]],
        ], $this->auth())->json();

        $response = $this->postJson("/api/orders/{$order['id']}/cancel", [], $this->auth());

        $response->assertOk()
            ->assertJsonPath('status', 'cancelled');
    }

    #[Test]
    public function it_cancels_a_confirmed_order(): void
    {
        $order = $this->postJson('/api/orders', [
            'items' => [['product_id' => $this->product->id, 'quantity' => 3]],
        ], $this->auth())->json();

        $this->postJson("/api/orders/{$order['id']}/confirm", [], $this->auth());
        $response = $this->postJson("/api/orders/{$order['id']}/cancel", [], $this->auth());

        $response->assertOk()
            ->assertJsonPath('status', 'cancelled');
    }

    #[Test]
    public function it_rejects_double_confirm(): void
    {
        $order = $this->postJson('/api/orders', [
            'items' => [['product_id' => $this->product->id, 'quantity' => 1]],
        ], $this->auth())->json();

        $this->postJson("/api/orders/{$order['id']}/confirm", [], $this->auth());
        $response = $this->postJson("/api/orders/{$order['id']}/confirm", [], $this->auth());

        $response->assertStatus(422);
    }

    #[Test]
    public function it_rejects_fulfill_on_draft_order(): void
    {
        $order = $this->postJson('/api/orders', [
            'items' => [['product_id' => $this->product->id, 'quantity' => 1]],
        ], $this->auth())->json();

        $response = $this->postJson("/api/orders/{$order['id']}/fulfill", [], $this->auth());

        $response->assertStatus(422);
    }

    #[Test]
    public function it_returns_404_for_unknown_order(): void
    {
        $this->getJson('/api/orders/00000000-0000-0000-0000-000000000000', $this->auth())
            ->assertStatus(404);
    }

    #[Test]
    public function it_filters_orders_by_status(): void
    {
        $this->postJson('/api/orders', [
            'items' => [['product_id' => $this->product->id, 'quantity' => 1]],
        ], $this->auth());

        $response = $this->getJson('/api/orders?status=draft', $this->auth());

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    #[Test]
    public function it_requires_authentication(): void
    {
        $this->getJson('/api/orders')->assertStatus(401);
        $this->postJson('/api/orders', [])->assertStatus(401);
    }
}
