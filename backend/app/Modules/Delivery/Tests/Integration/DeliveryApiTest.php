<?php

namespace App\Modules\Delivery\Tests\Integration;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Delivery\Models\Delivery;
use App\Modules\Inventory\Services\StockService;
use App\Modules\Orders\Services\OrderService;
use App\Modules\Tenants\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DeliveryApiTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private string $token;
    private string $orderId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Livraison Shop', 'slug' => 'livraison-shop', 'plan' => 'starter', 'status' => 'active',
        ]);
        $this->user  = User::create([
            'name' => 'Manager', 'email' => 'mgr@livraison.com',
            'password' => Hash::make('Secret123!'), 'tenant_id' => $this->tenant->id,
        ]);
        $this->token = $this->user->createToken('api')->plainTextToken;

        $product = Product::create([
            'tenant_id' => $this->tenant->id, 'sku' => 'LIV-001',
            'name' => 'Article', 'price_amount' => 8000, 'price_currency' => 'XOF', 'status' => 'active',
        ]);
        $stock = $this->app->make(StockService::class)->findOrCreate($this->tenant->id, $product->id, null);
        $this->app->make(StockService::class)->moveIn($stock, 50);

        $order = $this->app->make(OrderService::class)->create(
            ['items' => [['product_id' => $product->id, 'quantity' => 1]]],
            $this->tenant->id,
            $this->user->id,
        );
        $this->orderId = $order->id;
    }

    private function auth(): array { return ['Authorization' => "Bearer {$this->token}"]; }

    private function createDelivery(array $extra = []): array
    {
        $res = $this->postJson('/api/deliveries', array_merge(
            ['order_id' => $this->orderId, 'carrier' => 'DHL'],
            $extra,
        ), $this->auth());
        $res->assertCreated();
        return $res->json('data');
    }

    #[Test]
    public function it_creates_a_delivery(): void
    {
        $res = $this->postJson('/api/deliveries', [
            'order_id'        => $this->orderId,
            'carrier'         => 'DHL',
            'tracking_number' => 'DHL-2026-001',
            'notes'           => 'Fragile',
        ], $this->auth());

        $res->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.carrier', 'DHL')
            ->assertJsonPath('data.tracking_number', 'DHL-2026-001');

        $this->assertDatabaseHas('deliveries', ['order_id' => $this->orderId]);
    }

    #[Test]
    public function it_creates_a_standalone_delivery_without_order(): void
    {
        $res = $this->postJson('/api/deliveries', [
            'carrier' => 'FedEx',
            'notes'   => 'Sans commande liée',
        ], $this->auth());

        $res->assertCreated()->assertJsonPath('data.order_id', null);
    }

    #[Test]
    public function it_rejects_order_from_another_tenant(): void
    {
        $other = Tenant::create(['name' => 'Other', 'slug' => 'other-x', 'plan' => 'starter', 'status' => 'active']);
        $user2 = User::create(['name' => 'U2', 'email' => 'u2@other.com', 'password' => Hash::make('p'), 'tenant_id' => $other->id]);
        $product2 = Product::create(['tenant_id' => $other->id, 'sku' => 'O-1', 'name' => 'P', 'price_amount' => 1000, 'price_currency' => 'XOF', 'status' => 'active']);
        $stock2   = $this->app->make(StockService::class)->findOrCreate($other->id, $product2->id, null);
        $this->app->make(StockService::class)->moveIn($stock2, 10);
        $otherOrder = $this->app->make(OrderService::class)->create(['items' => [['product_id' => $product2->id, 'quantity' => 1]]], $other->id, $user2->id);

        $res = $this->postJson('/api/deliveries', ['order_id' => $otherOrder->id], $this->auth());
        $res->assertNotFound();
    }

    #[Test]
    public function it_shows_a_delivery(): void
    {
        $data = $this->createDelivery();
        $this->getJson("/api/deliveries/{$data['id']}", $this->auth())
            ->assertOk()
            ->assertJsonPath('data.id', $data['id']);
    }

    #[Test]
    public function it_dispatches_a_delivery(): void
    {
        $data = $this->createDelivery();

        $this->postJson("/api/deliveries/{$data['id']}/dispatch", [], $this->auth())
            ->assertOk()
            ->assertJsonPath('data.status', 'dispatched');
    }

    #[Test]
    public function it_cannot_dispatch_twice(): void
    {
        $data = $this->createDelivery();
        $this->postJson("/api/deliveries/{$data['id']}/dispatch", [], $this->auth())->assertOk();

        $this->postJson("/api/deliveries/{$data['id']}/dispatch", [], $this->auth())
            ->assertUnprocessable();
    }

    #[Test]
    public function it_confirms_delivery(): void
    {
        $data = $this->createDelivery();
        $this->postJson("/api/deliveries/{$data['id']}/dispatch", [], $this->auth());

        $this->postJson("/api/deliveries/{$data['id']}/deliver", [], $this->auth())
            ->assertOk()
            ->assertJsonPath('data.status', 'delivered');
    }

    #[Test]
    public function it_marks_delivery_as_failed(): void
    {
        $data = $this->createDelivery();
        $this->postJson("/api/deliveries/{$data['id']}/dispatch", [], $this->auth());

        $this->postJson("/api/deliveries/{$data['id']}/fail", ['reason' => 'Adresse incorrecte'], $this->auth())
            ->assertOk()
            ->assertJsonPath('data.status', 'failed')
            ->assertJsonPath('data.failed_reason', 'Adresse incorrecte');
    }

    #[Test]
    public function fail_requires_reason(): void
    {
        $data = $this->createDelivery();
        $this->postJson("/api/deliveries/{$data['id']}/fail", [], $this->auth())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['reason']);
    }

    #[Test]
    public function it_lists_deliveries_for_an_order_via_nested_route(): void
    {
        $this->createDelivery();
        $this->createDelivery(['carrier' => 'Colissimo']);

        $this->getJson("/api/orders/{$this->orderId}/deliveries", $this->auth())
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    #[Test]
    public function it_requires_auth(): void
    {
        $this->getJson('/api/deliveries')->assertUnauthorized();
        $this->postJson('/api/deliveries', [])->assertUnauthorized();
    }

    #[Test]
    public function it_cannot_access_another_tenants_delivery(): void
    {
        $other    = Tenant::create(['name' => 'Other2', 'slug' => 'other2', 'plan' => 'starter', 'status' => 'active']);
        $delivery = Delivery::create([
            'tenant_id' => $other->id,
            'status'    => 'pending',
        ]);

        $this->getJson("/api/deliveries/{$delivery->id}", $this->auth())->assertNotFound();
    }
}
