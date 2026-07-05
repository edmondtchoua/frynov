<?php

namespace App\Modules\Orders\Tests\Integration;

use App\Models\User;
use App\Modules\Billing\Models\Plan;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
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
    public function it_orders_a_variant_through_the_full_stock_chain(): void
    {
        // Full chain: variable product → variant → stock-in (variant) → order (variant)
        // → confirm (reserves variant stock) → fulfill (consumes variant stock).
        $variableProduct = Product::create([
            'tenant_id' => $this->tenant->id, 'sku' => 'VAR-PROD-1',
            'name' => 'Bassine', 'price_amount' => 4200_00, 'price_currency' => 'XOF',
            'status' => 'active', 'has_variants' => true, 'product_type' => 'variable',
        ]);
        $variant = ProductVariant::create([
            'tenant_id' => $this->tenant->id, 'product_id' => $variableProduct->id,
            'sku' => 'VAR-PROD-1-V1', 'label' => '30L / Rouge',
            'price_amount' => 5000_00, 'price_currency' => 'XOF', 'is_active' => true,
        ]);

        // Stock-in on the VARIANT (not the product)
        $variantStock = $this->app->make(StockService::class)
            ->findOrCreate($this->tenant->id, $variableProduct->id, $variant->id);
        $this->app->make(StockService::class)->moveIn($variantStock, 8);

        // Order the variant
        $order = $this->postJson('/api/orders', [
            'items' => [[
                'product_id' => $variableProduct->id,
                'variant_id' => $variant->id,
                'quantity'   => 3,
            ]],
        ], $this->auth())->json();

        // Price resolved from the VARIANT (5000), not the product (4200)
        $this->assertSame(15000_00, $order['total_amount']);

        $this->postJson("/api/orders/{$order['id']}/confirm", [], $this->auth())->assertOk();
        // Reservation lands on the variant stock
        $variantStock->refresh();
        $this->assertSame(3, $variantStock->reserved_quantity);

        $this->postJson("/api/orders/{$order['id']}/fulfill", [], $this->auth())->assertOk();
        // Variant stock consumed: 8 - 3 = 5, reservation cleared
        $variantStock->refresh();
        $this->assertSame(5, $variantStock->quantity);
        $this->assertSame(0, $variantStock->reserved_quantity);

        // Product-level stock was never touched
        $productStock = $this->app->make(StockService::class)
            ->findOrCreate($this->tenant->id, $variableProduct->id, null);
        $this->assertSame(0, $productStock->quantity);
    }

    #[Test]
    public function it_uses_the_tenant_configured_currency(): void
    {
        // Tenant configured in XAF (Central Africa) must get XAF orders,
        // not a hardcoded XOF. Regression for the hardcoded-currency bug.
        $this->tenant->update(['settings' => ['currency' => 'XAF']]);

        $response = $this->postJson('/api/orders', [
            'items' => [['product_id' => $this->product->id, 'quantity' => 1]],
        ], $this->auth());

        $response->assertStatus(201)
            ->assertJsonPath('currency', 'XAF');
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
