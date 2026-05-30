<?php

namespace App\Modules\Orders\Tests\Modular;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Exceptions\InsufficientStockException;
use App\Modules\Inventory\Services\StockService;
use App\Modules\Orders\Services\OrderService;
use App\Modules\Tenants\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Cross-module tests validating Order ↔ Stock interactions end-to-end.
 */
class OrderModuleTest extends TestCase
{
    use RefreshDatabase;

    private OrderService $orderService;
    private StockService $stockService;
    private Tenant $tenant;
    private User $user;
    private Product $productA;
    private Product $productB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orderService = $this->app->make(OrderService::class);
        $this->stockService = $this->app->make(StockService::class);

        $this->tenant = Tenant::create([
            'name'   => 'Boutique Dakar',
            'slug'   => 'boutique-dakar',
            'plan'   => 'starter',
            'status' => 'active',
        ]);

        $this->user = User::create([
            'name'      => 'Caissier',
            'email'     => 'caissier@dakar.sn',
            'password'  => Hash::make('Secret123!'),
            'tenant_id' => $this->tenant->id,
        ]);

        $this->productA = Product::create([
            'tenant_id'      => $this->tenant->id,
            'sku'            => 'ROB-001',
            'name'           => 'Robe Wax',
            'price_amount'   => 15000,
            'price_currency' => 'XOF',
            'status'         => 'active',
        ]);

        $this->productB = Product::create([
            'tenant_id'      => $this->tenant->id,
            'sku'            => 'CHN-001',
            'name'           => 'Chaussures',
            'price_amount'   => 20000,
            'price_currency' => 'XOF',
            'status'         => 'active',
        ]);

        $stockA = $this->stockService->findOrCreate($this->tenant->id, $this->productA->id, null);
        $stockB = $this->stockService->findOrCreate($this->tenant->id, $this->productB->id, null);
        $this->stockService->moveIn($stockA, 20);
        $this->stockService->moveIn($stockB, 10);
    }

    #[Test]
    public function full_order_lifecycle_updates_stock_correctly(): void
    {
        $stockA = $this->stockService->findOrCreate($this->tenant->id, $this->productA->id, null);
        $stockB = $this->stockService->findOrCreate($this->tenant->id, $this->productB->id, null);

        // Create draft — no stock impact
        $order = $this->orderService->create([
            'items' => [
                ['product_id' => $this->productA->id, 'quantity' => 3],
                ['product_id' => $this->productB->id, 'quantity' => 2],
            ],
        ], $this->tenant->id, $this->user->id);

        $stockA->refresh(); $stockB->refresh();
        $this->assertEquals(20, $stockA->available());
        $this->assertEquals(10, $stockB->available());

        // Confirm — reserves stock
        $order = $this->orderService->confirm($order, $this->user->id);

        $stockA->refresh(); $stockB->refresh();
        $this->assertEquals(17, $stockA->available()); // 20 - 3 reserved
        $this->assertEquals(8,  $stockB->available()); // 10 - 2 reserved

        // Fulfill — consumes stock + releases reservation
        $order = $this->orderService->fulfill($order, $this->user->id);

        $stockA->refresh(); $stockB->refresh();
        $this->assertEquals(17, $stockA->quantity);    // 20 - 3 sold
        $this->assertEquals(0,  $stockA->reserved_quantity);
        $this->assertEquals(8,  $stockB->quantity);    // 10 - 2 sold
        $this->assertEquals(0,  $stockB->reserved_quantity);

        $this->assertEquals('fulfilled', $order->status);
        $this->assertNotNull($order->fulfilled_at);
    }

    #[Test]
    public function cancelling_confirmed_order_restores_full_availability(): void
    {
        $stockA = $this->stockService->findOrCreate($this->tenant->id, $this->productA->id, null);

        $order = $this->orderService->create([
            'items' => [['product_id' => $this->productA->id, 'quantity' => 5]],
        ], $this->tenant->id, $this->user->id);

        $order = $this->orderService->confirm($order, $this->user->id);
        $stockA->refresh();
        $this->assertEquals(5, $stockA->reserved_quantity);

        $order = $this->orderService->cancel($order, $this->user->id);

        $stockA->refresh();
        $this->assertEquals(0, $stockA->reserved_quantity);
        $this->assertEquals(20, $stockA->available());
        $this->assertEquals('cancelled', $order->status);
    }

    #[Test]
    public function confirm_fails_when_stock_is_insufficient(): void
    {
        $order = $this->orderService->create([
            'items' => [['product_id' => $this->productA->id, 'quantity' => 999]],
        ], $this->tenant->id, $this->user->id);

        $this->expectException(InsufficientStockException::class);
        $this->orderService->confirm($order, $this->user->id);
    }

    #[Test]
    public function two_concurrent_orders_cannot_oversell(): void
    {
        // Confirm order 1 with all available stock
        $order1 = $this->orderService->create([
            'items' => [['product_id' => $this->productB->id, 'quantity' => 10]],
        ], $this->tenant->id, $this->user->id);
        $this->orderService->confirm($order1, $this->user->id);

        // Second order for the same product should fail
        $order2 = $this->orderService->create([
            'items' => [['product_id' => $this->productB->id, 'quantity' => 1]],
        ], $this->tenant->id, $this->user->id);

        $this->expectException(InsufficientStockException::class);
        $this->orderService->confirm($order2, $this->user->id);
    }
}
