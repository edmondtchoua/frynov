<?php

namespace App\Modules\Payments\Tests\Unit;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\Warehouse;
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

class PaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    private PaymentService $service;
    private OrderService $orders;
    private Tenant $tenant;
    private User $user;
    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = $this->app->make(PaymentService::class);
        $this->orders  = $this->app->make(OrderService::class);

        $this->tenant = Tenant::create([
            'name' => 'Boutique Test', 'slug' => 'boutique-test',
            'plan' => 'starter', 'status' => 'active',
        ]);

        $this->user = User::create([
            'name' => 'Manager', 'email' => 'manager@test.com',
            'password' => Hash::make('Secret123!'), 'tenant_id' => $this->tenant->id,
        ]);

        $product = Product::create([
            'tenant_id' => $this->tenant->id, 'sku' => 'TST-001',
            'name' => 'Produit Test', 'price_amount' => 10000,
            'price_currency' => 'EUR', 'status' => 'active',
        ]);

        $stock = $this->app->make(StockService::class)->findOrCreate($this->tenant->id, $product->id, null);
        $this->app->make(StockService::class)->moveIn($stock, 100);

        $this->order = $this->orders->create([
            'items' => [['product_id' => $product->id, 'quantity' => 3]],
        ], $this->tenant->id, $this->user->id);
        // total = 3 × 10 000 = 30 000 cents
    }

    #[Test]
    public function it_records_a_cash_payment(): void
    {
        $payment = $this->service->record([
            'order_id'     => $this->order->id,
            'amount_cents' => 15000,
            'currency'     => 'EUR',
            'method'       => Payment::METHOD_CASH,
        ], $this->tenant->id, $this->user->id);

        $this->assertInstanceOf(Payment::class, $payment);
        $this->assertEquals(15000, $payment->amount_cents);
        $this->assertEquals(Payment::METHOD_CASH, $payment->method);
        $this->assertDatabaseHas('payments', ['id' => $payment->id]);
    }

    #[Test]
    public function it_records_a_mobile_money_payment_with_reference(): void
    {
        $payment = $this->service->record([
            'order_id'     => $this->order->id,
            'amount_cents' => 30000,
            'currency'     => 'EUR',
            'method'       => Payment::METHOD_MOBILE_MONEY,
            'reference'    => 'OM-TX-2026-001',
        ], $this->tenant->id, $this->user->id);

        $this->assertEquals('OM-TX-2026-001', $payment->reference);
        $this->assertEquals(Payment::METHOD_MOBILE_MONEY, $payment->method);
    }

    #[Test]
    public function it_computes_balance_from_multiple_payments(): void
    {
        $this->service->record(['order_id' => $this->order->id, 'amount_cents' => 10000, 'currency' => 'EUR', 'method' => 'cash'], $this->tenant->id, $this->user->id);
        $this->service->record(['order_id' => $this->order->id, 'amount_cents' => 10000, 'currency' => 'EUR', 'method' => 'card'], $this->tenant->id, $this->user->id);

        $this->assertEquals(20000, $this->service->balance($this->order));
    }

    #[Test]
    public function it_detects_fully_paid_order(): void
    {
        $this->assertFalse($this->service->isFullyPaid($this->order));

        $this->service->record(['order_id' => $this->order->id, 'amount_cents' => 30000, 'currency' => 'EUR', 'method' => 'cash'], $this->tenant->id, $this->user->id);

        $this->assertTrue($this->service->isFullyPaid($this->order));
    }

    #[Test]
    public function it_detects_partially_paid_order(): void
    {
        $this->service->record(['order_id' => $this->order->id, 'amount_cents' => 15000, 'currency' => 'EUR', 'method' => 'cash'], $this->tenant->id, $this->user->id);

        $this->assertFalse($this->service->isFullyPaid($this->order));
        $this->assertEquals(15000, $this->service->balance($this->order));
    }

    #[Test]
    public function it_voids_a_payment(): void
    {
        $payment = $this->service->record(['order_id' => $this->order->id, 'amount_cents' => 5000, 'currency' => 'EUR', 'method' => 'cash'], $this->tenant->id, $this->user->id);
        $this->service->void($payment);

        $this->assertSoftDeleted('payments', ['id' => $payment->id]);
        // Voided payment not counted in balance
        $this->assertEquals(0, $this->service->balance($this->order));
    }

    #[Test]
    public function it_lists_payments_for_an_order(): void
    {
        $this->service->record(['order_id' => $this->order->id, 'amount_cents' => 10000, 'currency' => 'EUR', 'method' => 'cash'], $this->tenant->id, $this->user->id);
        $this->service->record(['order_id' => $this->order->id, 'amount_cents' => 10000, 'currency' => 'EUR', 'method' => 'card'], $this->tenant->id, $this->user->id);

        $payments = $this->service->listForOrder($this->order);
        $this->assertCount(2, $payments);
    }

    #[Test]
    public function it_records_standalone_payment_without_order(): void
    {
        $payment = $this->service->record([
            'amount_cents' => 5000,
            'currency'     => 'EUR',
            'method'       => Payment::METHOD_TRANSFER,
            'note'         => 'Paiement avance',
        ], $this->tenant->id, $this->user->id);

        $this->assertNull($payment->order_id);
        $this->assertEquals(5000, $payment->amount_cents);
    }

    #[Test]
    public function list_filters_payments_by_warehouse(): void
    {
        // Sprint 20 multi-sites: list payments scoped to a single site/warehouse.
        $whA = Warehouse::create(['tenant_id' => $this->tenant->id, 'name' => 'Dépôt A', 'code' => 'WH-A', 'is_default' => true]);
        $whB = Warehouse::create(['tenant_id' => $this->tenant->id, 'name' => 'Dépôt B', 'code' => 'WH-B', 'is_default' => false]);

        $payA = $this->service->record(['order_id' => $this->order->id, 'amount_cents' => 10000, 'currency' => 'EUR', 'method' => 'cash'], $this->tenant->id, $this->user->id);
        $payA->warehouse_id = $whA->id;
        $payA->save();

        $payB = $this->service->record(['order_id' => $this->order->id, 'amount_cents' => 10000, 'currency' => 'EUR', 'method' => 'cash'], $this->tenant->id, $this->user->id);
        $payB->warehouse_id = $whB->id;
        $payB->save();

        $onlyA = $this->service->list($this->tenant->id, ['warehouse_ids' => [$whA->id]]);
        $this->assertSame([$payA->id], collect($onlyA->items())->pluck('id')->all());

        $this->assertCount(2, $this->service->list($this->tenant->id, [])->items());
    }
}
