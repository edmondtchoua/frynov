<?php

namespace App\Modules\Delivery\Tests\Unit;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Delivery\Models\Delivery;
use App\Modules\Delivery\Services\DeliveryService;
use App\Modules\Inventory\Services\StockService;
use App\Modules\Orders\Services\OrderService;
use App\Modules\Tenants\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DeliveryServiceTest extends TestCase
{
    use RefreshDatabase;

    private DeliveryService $service;
    private Tenant $tenant;
    private User $user;
    private string $orderId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = $this->app->make(DeliveryService::class);

        $this->tenant = Tenant::create([
            'name' => 'Test Shop', 'slug' => 'test-shop', 'plan' => 'starter', 'status' => 'active',
        ]);

        $this->user = User::create([
            'name' => 'Staff', 'email' => 'staff@test.com',
            'password' => Hash::make('pass'), 'tenant_id' => $this->tenant->id,
        ]);

        $product = Product::create([
            'tenant_id' => $this->tenant->id, 'sku' => 'D-001',
            'name' => 'Produit', 'price_amount' => 5000, 'price_currency' => 'XOF', 'status' => 'active',
        ]);

        $stock = $this->app->make(StockService::class)->findOrCreate($this->tenant->id, $product->id, null);
        $this->app->make(StockService::class)->moveIn($stock, 20);

        $order = $this->app->make(OrderService::class)->create(
            ['items' => [['product_id' => $product->id, 'quantity' => 2]]],
            $this->tenant->id,
            $this->user->id,
        );
        $this->orderId = $order->id;
    }

    private function makeDelivery(array $extra = []): Delivery
    {
        return $this->service->create(
            array_merge(['order_id' => $this->orderId], $extra),
            $this->tenant->id,
            $this->user->id,
        );
    }

    #[Test]
    public function it_creates_a_delivery_with_pending_status(): void
    {
        $delivery = $this->makeDelivery(['carrier' => 'DHL', 'tracking_number' => 'DHL-001']);

        $this->assertInstanceOf(Delivery::class, $delivery);
        $this->assertEquals(Delivery::STATUS_PENDING, $delivery->status);
        $this->assertEquals('DHL', $delivery->carrier);
        $this->assertDatabaseHas('deliveries', ['id' => $delivery->id, 'status' => 'pending']);
    }

    #[Test]
    public function it_dispatches_a_pending_delivery(): void
    {
        $delivery = $this->makeDelivery();
        $dispatched = $this->service->dispatch($delivery);

        $this->assertEquals(Delivery::STATUS_DISPATCHED, $dispatched->status);
        $this->assertNotNull($dispatched->dispatched_at);
    }

    #[Test]
    public function it_cannot_dispatch_an_already_dispatched_delivery(): void
    {
        $delivery = $this->makeDelivery();
        $this->service->dispatch($delivery);
        $delivery->refresh();

        $this->expectException(\DomainException::class);
        $this->service->dispatch($delivery);
    }

    #[Test]
    public function it_confirms_a_dispatched_delivery(): void
    {
        $delivery = $this->makeDelivery();
        $this->service->dispatch($delivery);
        $delivery->refresh();

        $delivered = $this->service->confirm($delivery);

        $this->assertEquals(Delivery::STATUS_DELIVERED, $delivered->status);
        $this->assertNotNull($delivered->delivered_at);
    }

    #[Test]
    public function it_cannot_confirm_a_pending_delivery(): void
    {
        $delivery = $this->makeDelivery();

        $this->expectException(\DomainException::class);
        $this->service->confirm($delivery);
    }

    #[Test]
    public function it_marks_a_delivery_as_failed(): void
    {
        $delivery = $this->makeDelivery();
        $this->service->dispatch($delivery);
        $delivery->refresh();

        $failed = $this->service->fail($delivery, 'Adresse introuvable');

        $this->assertEquals(Delivery::STATUS_FAILED, $failed->status);
        $this->assertEquals('Adresse introuvable', $failed->failed_reason);
        $this->assertNotNull($failed->failed_at);
    }

    #[Test]
    public function it_cannot_fail_an_already_delivered_delivery(): void
    {
        $delivery = $this->makeDelivery();
        $this->service->dispatch($delivery);
        $delivery->refresh();
        $this->service->confirm($delivery);
        $delivery->refresh();

        $this->expectException(\DomainException::class);
        $this->service->fail($delivery, 'Too late');
    }

    #[Test]
    public function it_lists_deliveries_for_an_order(): void
    {
        $this->makeDelivery();
        $this->makeDelivery(['carrier' => 'FedEx']);

        $list = $this->service->listForOrder($this->orderId, $this->tenant->id);
        $this->assertCount(2, $list);
    }

    #[Test]
    public function findOrFail_throws_for_wrong_tenant(): void
    {
        $delivery  = $this->makeDelivery();
        $otherTenant = Tenant::create(['name' => 'Other', 'slug' => 'other', 'plan' => 'starter', 'status' => 'active']);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        $this->service->findOrFail($delivery->id, $otherTenant->id);
    }
}
