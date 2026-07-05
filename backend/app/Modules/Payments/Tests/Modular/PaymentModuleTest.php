<?php

namespace App\Modules\Payments\Tests\Modular;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Services\StockService;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Services\OrderService;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Services\PaymentService;
use App\Modules\Tenants\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Cross-module: Payment ↔ Order integration
 */
class PaymentModuleTest extends TestCase
{
    use RefreshDatabase;

    private PaymentService $payments;
    private OrderService $orders;
    private Tenant $tenant;
    private User $user;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->payments = $this->app->make(PaymentService::class);
        $this->orders   = $this->app->make(OrderService::class);

        $this->tenant = Tenant::create(['name' => 'Test', 'slug' => 'test', 'plan' => 'starter', 'status' => 'active']);
        $this->user   = User::create(['name' => 'M', 'email' => 'm@t.com', 'password' => Hash::make('pass'), 'tenant_id' => $this->tenant->id]);

        $this->product = Product::create(['tenant_id' => $this->tenant->id, 'sku' => 'P1', 'name' => 'Produit', 'price_amount' => 10000, 'price_currency' => 'EUR', 'status' => 'active']);
        $stock = $this->app->make(StockService::class)->findOrCreate($this->tenant->id, $this->product->id, null);
        $this->app->make(StockService::class)->moveIn($stock, 50);
    }

    private function makeOrder(int $quantity = 2): Order
    {
        return $this->orders->create(
            ['items' => [['product_id' => $this->product->id, 'quantity' => $quantity]]],
            $this->tenant->id,
            $this->user->id,
        );
    }

    #[Test]
    public function order_is_not_fully_paid_before_any_payment(): void
    {
        $order = $this->makeOrder(2); // total = 20 000
        $this->assertEquals(0, $this->payments->balance($order));
        $this->assertFalse($this->payments->isFullyPaid($order));
    }

    #[Test]
    public function split_payments_sum_to_full_total(): void
    {
        $order = $this->makeOrder(3); // total = 30 000

        $this->payments->record(['order_id' => $order->id, 'amount_cents' => 10000, 'currency' => 'EUR', 'method' => 'cash'], $this->tenant->id, $this->user->id);
        $this->payments->record(['order_id' => $order->id, 'amount_cents' => 20000, 'currency' => 'EUR', 'method' => 'card'], $this->tenant->id, $this->user->id);

        $this->assertEquals(30000, $this->payments->balance($order));
        $this->assertTrue($this->payments->isFullyPaid($order));
    }

    #[Test]
    public function voiding_a_payment_reduces_balance(): void
    {
        $order = $this->makeOrder(2); // 20 000

        $p1 = $this->payments->record(['order_id' => $order->id, 'amount_cents' => 15000, 'currency' => 'EUR', 'method' => 'cash'], $this->tenant->id, $this->user->id);
        $this->payments->record(['order_id' => $order->id, 'amount_cents' => 5000, 'currency' => 'EUR', 'method' => 'card'], $this->tenant->id, $this->user->id);

        $this->assertEquals(20000, $this->payments->balance($order));
        $this->payments->void($p1);
        $this->assertEquals(5000, $this->payments->balance($order));
        $this->assertFalse($this->payments->isFullyPaid($order));
    }

    #[Test]
    public function order_payments_relation_returns_correct_count(): void
    {
        $order = $this->makeOrder();

        $this->payments->record(['order_id' => $order->id, 'amount_cents' => 5000, 'currency' => 'EUR', 'method' => 'cash'], $this->tenant->id, $this->user->id);
        $this->payments->record(['order_id' => $order->id, 'amount_cents' => 5000, 'currency' => 'EUR', 'method' => 'cash'], $this->tenant->id, $this->user->id);

        $order->refresh();
        $this->assertCount(2, $order->payments);
    }

    #[Test]
    public function payments_are_isolated_per_tenant(): void
    {
        $other = Tenant::create(['name' => 'Other', 'slug' => 'other', 'plan' => 'starter', 'status' => 'active']);

        $myOrder = $this->makeOrder();
        $this->payments->record(['order_id' => $myOrder->id, 'amount_cents' => 5000, 'currency' => 'EUR', 'method' => 'cash'], $this->tenant->id, $this->user->id);
        // Record a standalone payment for the other tenant
        $this->payments->record(['amount_cents' => 9000, 'currency' => 'EUR', 'method' => 'cash'], $other->id, $this->user->id);

        // balance only sums MY payments
        $this->assertEquals(5000, $this->payments->balance($myOrder));
        $total = Payment::where('tenant_id', $this->tenant->id)->sum('amount_cents');
        $this->assertEquals(5000, $total);
    }
}
