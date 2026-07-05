<?php

namespace App\Modules\Customers\Tests\Modular;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\Services\CustomerService;
use App\Modules\Inventory\Services\StockService;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Services\OrderService;
use App\Modules\Tenants\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Cross-module tests: Customer ↔ Orders integration.
 */
class CustomerModuleTest extends TestCase
{
    use RefreshDatabase;

    private CustomerService $customers;
    private OrderService $orders;
    private Tenant $tenant;
    private User $user;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customers = $this->app->make(CustomerService::class);
        $this->orders    = $this->app->make(OrderService::class);

        $this->tenant = Tenant::create([
            'name' => 'Boutique Test', 'slug' => 'boutique-test',
            'plan' => 'starter', 'status' => 'active',
        ]);

        $this->user = User::create([
            'name'      => 'Manager',
            'email'     => 'manager@test.com',
            'password'  => Hash::make('Secret123!'),
            'tenant_id' => $this->tenant->id,
        ]);

        $this->product = Product::create([
            'tenant_id'      => $this->tenant->id,
            'sku'            => 'TST-0001',
            'name'           => 'Produit Test',
            'price_amount'   => 10000,
            'price_currency' => 'XOF',
            'status'         => 'active',
        ]);

        $stock = $this->app->make(StockService::class)
            ->findOrCreate($this->tenant->id, $this->product->id, null);
        $this->app->make(StockService::class)->moveIn($stock, 100);
    }

    #[Test]
    public function an_order_can_be_linked_to_a_customer(): void
    {
        $customer = $this->customers->create([
            'name'  => 'Client Premium',
            'email' => 'premium@test.com',
        ], $this->tenant->id);

        $order = $this->orders->create([
            'customer_id' => $customer->id,
            'items'       => [['product_id' => $this->product->id, 'quantity' => 2]],
        ], $this->tenant->id, $this->user->id);

        $this->assertEquals($customer->id, $order->customer_id);
        $this->assertDatabaseHas('orders', [
            'id'          => $order->id,
            'customer_id' => $customer->id,
        ]);
    }

    #[Test]
    public function customer_orders_count_reflects_real_orders(): void
    {
        $customer = $this->customers->create(['name' => 'Active Client'], $this->tenant->id);

        $this->orders->create([
            'customer_id' => $customer->id,
            'items'       => [['product_id' => $this->product->id, 'quantity' => 1]],
        ], $this->tenant->id, $this->user->id);

        $this->orders->create([
            'customer_id' => $customer->id,
            'items'       => [['product_id' => $this->product->id, 'quantity' => 1]],
        ], $this->tenant->id, $this->user->id);

        $loaded = $this->customers->findOrFail($customer->id, $this->tenant->id);
        $loaded->loadCount('orders');

        $this->assertEquals(2, $loaded->orders_count);
    }

    #[Test]
    public function deleting_customer_does_not_cascade_to_orders(): void
    {
        $customer = $this->customers->create(['name' => 'Supprimé'], $this->tenant->id);

        $order = $this->orders->create([
            'customer_id' => $customer->id,
            'items'       => [['product_id' => $this->product->id, 'quantity' => 1]],
        ], $this->tenant->id, $this->user->id);

        $this->customers->delete($customer);

        // Order still exists, customer_id preserved as historical record
        $this->assertDatabaseHas('orders', ['id' => $order->id]);
        $this->assertSoftDeleted('customers', ['id' => $customer->id]);
    }

    #[Test]
    public function customer_orders_endpoint_returns_their_orders_only(): void
    {
        $c1 = $this->customers->create(['name' => 'Client A'], $this->tenant->id);
        $c2 = $this->customers->create(['name' => 'Client B'], $this->tenant->id);

        $this->orders->create([
            'customer_id' => $c1->id,
            'items'       => [['product_id' => $this->product->id, 'quantity' => 1]],
        ], $this->tenant->id, $this->user->id);

        $this->orders->create([
            'customer_id' => $c2->id,
            'items'       => [['product_id' => $this->product->id, 'quantity' => 1]],
        ], $this->tenant->id, $this->user->id);

        // Each customer has only their own orders
        $c1Orders = $c1->orders()->get();
        $c2Orders = $c2->orders()->get();

        $this->assertCount(1, $c1Orders);
        $this->assertCount(1, $c2Orders);
        $this->assertNotEquals($c1Orders->first()->id, $c2Orders->first()->id);
    }
}
