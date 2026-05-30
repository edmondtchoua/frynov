<?php

namespace App\Modules\Orders\Tests\Unit;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Services\StockService;
use App\Modules\Orders\Exceptions\OrderStateException;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Services\OrderService;
use App\Modules\Tenants\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OrderServiceTest extends TestCase
{
    use RefreshDatabase;

    private OrderService $service;
    private Tenant $tenant;
    private User $user;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = $this->app->make(OrderService::class);

        $this->tenant = Tenant::create([
            'name'   => 'Boutique Test',
            'slug'   => 'boutique-test',
            'plan'   => 'starter',
            'status' => 'active',
        ]);

        $this->user = User::create([
            'name'      => 'Manager',
            'email'     => 'manager@test.sn',
            'password'  => Hash::make('Secret123!'),
            'tenant_id' => $this->tenant->id,
        ]);

        $this->product = Product::create([
            'tenant_id'      => $this->tenant->id,
            'sku'            => 'VET-0001',
            'name'           => 'Boubou Sénégalais',
            'price_amount'   => 25000,
            'price_currency' => 'XOF',
            'status'         => 'active',
        ]);

        // Seed stock
        $stockService = $this->app->make(StockService::class);
        $stock = $stockService->findOrCreate($this->tenant->id, $this->product->id, null);
        $stockService->moveIn($stock, 50);
    }

    #[Test]
    public function it_creates_a_draft_order(): void
    {
        $order = $this->service->create([
            'items' => [[
                'product_id' => $this->product->id,
                'quantity'   => 2,
            ]],
        ], $this->tenant->id, $this->user->id);

        $this->assertEquals(Order::STATUS_DRAFT, $order->status);
        $this->assertStringStartsWith('ORD-', $order->number);
        $this->assertCount(1, $order->lines);
        $this->assertEquals(2 * 25000, $order->total_amount);
    }

    #[Test]
    public function it_generates_sequential_order_numbers(): void
    {
        $order1 = $this->service->create(['items' => [['product_id' => $this->product->id, 'quantity' => 1]]], $this->tenant->id, $this->user->id);
        $order2 = $this->service->create(['items' => [['product_id' => $this->product->id, 'quantity' => 1]]], $this->tenant->id, $this->user->id);

        $this->assertNotEquals($order1->number, $order2->number);
        $this->assertEquals('ORD-00001', $order1->number);
        $this->assertEquals('ORD-00002', $order2->number);
    }

    #[Test]
    public function it_confirms_a_draft_order_and_reserves_stock(): void
    {
        $stockService = $this->app->make(StockService::class);
        $stock = $stockService->findOrCreate($this->tenant->id, $this->product->id, null);
        $this->assertEquals(0, $stock->reserved_quantity);

        $order = $this->service->create([
            'items' => [['product_id' => $this->product->id, 'quantity' => 3]],
        ], $this->tenant->id, $this->user->id);

        $this->service->confirm($order, $this->user->id);

        $stock->refresh();
        $this->assertEquals(3, $stock->reserved_quantity);
        $this->assertEquals(47, $stock->available());
    }

    #[Test]
    public function it_fulfills_a_confirmed_order_and_decrements_stock(): void
    {
        $stockService = $this->app->make(StockService::class);
        $stock = $stockService->findOrCreate($this->tenant->id, $this->product->id, null);

        $order = $this->service->create([
            'items' => [['product_id' => $this->product->id, 'quantity' => 5]],
        ], $this->tenant->id, $this->user->id);

        $order = $this->service->confirm($order, $this->user->id);
        $order = $this->service->fulfill($order, $this->user->id);

        $this->assertEquals(Order::STATUS_FULFILLED, $order->status);
        $this->assertNotNull($order->fulfilled_at);

        $stock->refresh();
        $this->assertEquals(45, $stock->quantity);   // 50 - 5
        $this->assertEquals(0, $stock->reserved_quantity); // reservation released
    }

    #[Test]
    public function it_cancels_a_draft_order_without_touching_stock(): void
    {
        $stockService = $this->app->make(StockService::class);
        $stock = $stockService->findOrCreate($this->tenant->id, $this->product->id, null);
        $qtyBefore = $stock->quantity;

        $order = $this->service->create([
            'items' => [['product_id' => $this->product->id, 'quantity' => 2]],
        ], $this->tenant->id, $this->user->id);

        $order = $this->service->cancel($order, $this->user->id);

        $this->assertEquals(Order::STATUS_CANCELLED, $order->status);
        $stock->refresh();
        $this->assertEquals($qtyBefore, $stock->quantity);
        $this->assertEquals(0, $stock->reserved_quantity);
    }

    #[Test]
    public function it_cancels_a_confirmed_order_and_releases_reservation(): void
    {
        $stockService = $this->app->make(StockService::class);
        $stock = $stockService->findOrCreate($this->tenant->id, $this->product->id, null);

        $order = $this->service->create([
            'items' => [['product_id' => $this->product->id, 'quantity' => 4]],
        ], $this->tenant->id, $this->user->id);

        $order = $this->service->confirm($order, $this->user->id);
        $stock->refresh();
        $this->assertEquals(4, $stock->reserved_quantity);

        $order = $this->service->cancel($order, $this->user->id);

        $stock->refresh();
        $this->assertEquals(0, $stock->reserved_quantity);
        $this->assertEquals(50, $stock->available());
    }

    #[Test]
    public function it_rejects_confirm_on_non_draft_order(): void
    {
        $order = $this->service->create([
            'items' => [['product_id' => $this->product->id, 'quantity' => 1]],
        ], $this->tenant->id, $this->user->id);

        $order = $this->service->confirm($order, $this->user->id);

        $this->expectException(OrderStateException::class);
        $this->service->confirm($order, $this->user->id);
    }

    #[Test]
    public function it_rejects_fulfill_on_draft_order(): void
    {
        $order = $this->service->create([
            'items' => [['product_id' => $this->product->id, 'quantity' => 1]],
        ], $this->tenant->id, $this->user->id);

        $this->expectException(OrderStateException::class);
        $this->service->fulfill($order, $this->user->id);
    }

    #[Test]
    public function it_uses_custom_unit_price_when_provided(): void
    {
        $order = $this->service->create([
            'items' => [[
                'product_id'       => $this->product->id,
                'quantity'         => 2,
                'unit_price_cents' => 20000, // discounted
            ]],
        ], $this->tenant->id, $this->user->id);

        $this->assertEquals(40000, $order->total_amount);
        $this->assertEquals(20000, $order->lines->first()->unit_price_cents);
    }
}
