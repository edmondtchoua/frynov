<?php

namespace App\Modules\Delivery\Tests\Modular;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Delivery\Models\Delivery;
use App\Modules\Delivery\Services\DeliveryService;
use App\Modules\Inventory\Services\StockService;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Services\OrderService;
use App\Modules\Tenants\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Cross-module: Delivery ↔ Order integration
 */
class DeliveryModuleTest extends TestCase
{
    use RefreshDatabase;

    private DeliveryService $deliveries;
    private OrderService $orders;
    private Tenant $tenant;
    private User $user;
    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        $this->deliveries = $this->app->make(DeliveryService::class);
        $this->orders     = $this->app->make(OrderService::class);

        $this->tenant = Tenant::create([
            'name' => 'Cross Test', 'slug' => 'cross-test', 'plan' => 'starter', 'status' => 'active',
        ]);
        $this->user = User::create([
            'name' => 'Staff', 'email' => 'staff@cross.com',
            'password' => Hash::make('pass'), 'tenant_id' => $this->tenant->id,
        ]);

        $product = Product::create([
            'tenant_id' => $this->tenant->id, 'sku' => 'X-001',
            'name' => 'Article', 'price_amount' => 2000, 'price_currency' => 'XOF', 'status' => 'active',
        ]);

        $stock = $this->app->make(StockService::class)->findOrCreate($this->tenant->id, $product->id, null);
        $this->app->make(StockService::class)->moveIn($stock, 30);

        $this->order = $this->orders->create(
            ['items' => [['product_id' => $product->id, 'quantity' => 1]]],
            $this->tenant->id,
            $this->user->id,
        );
    }

    private function makeDelivery(): Delivery
    {
        return $this->deliveries->create(
            ['order_id' => $this->order->id],
            $this->tenant->id,
            $this->user->id,
        );
    }

    #[Test]
    public function full_delivery_lifecycle_pending_to_delivered(): void
    {
        $delivery = $this->makeDelivery();
        $this->assertEquals('pending', $delivery->status);

        $delivery = $this->deliveries->dispatch($delivery);
        $this->assertEquals('dispatched', $delivery->status);
        $this->assertNotNull($delivery->dispatched_at);

        $delivery = $this->deliveries->confirm($delivery);
        $this->assertEquals('delivered', $delivery->status);
        $this->assertNotNull($delivery->delivered_at);
    }

    #[Test]
    public function full_delivery_lifecycle_pending_to_failed(): void
    {
        $delivery = $this->makeDelivery();
        $delivery = $this->deliveries->dispatch($delivery);
        $delivery = $this->deliveries->fail($delivery, 'Client absent');

        $this->assertEquals('failed', $delivery->status);
        $this->assertEquals('Client absent', $delivery->failed_reason);
    }

    #[Test]
    public function order_deliveries_relation_returns_correct_count(): void
    {
        $this->makeDelivery();
        $this->makeDelivery();

        $this->order->refresh();
        $this->assertCount(2, $this->order->deliveries);
    }

    #[Test]
    public function deliveries_are_isolated_per_tenant(): void
    {
        $other = Tenant::create(['name' => 'Other', 'slug' => 'other-iso', 'plan' => 'starter', 'status' => 'active']);

        $this->makeDelivery();
        Delivery::create(['tenant_id' => $other->id, 'status' => 'pending']);

        $myDeliveries    = Delivery::where('tenant_id', $this->tenant->id)->count();
        $otherDeliveries = Delivery::where('tenant_id', $other->id)->count();

        $this->assertEquals(1, $myDeliveries);
        $this->assertEquals(1, $otherDeliveries);
    }

    #[Test]
    public function delivery_is_linked_to_order(): void
    {
        $delivery = $this->makeDelivery();
        $delivery->load('order');

        $this->assertEquals($this->order->id, $delivery->order->id);
        $this->assertEquals($this->order->number, $delivery->order->number);
    }
}
