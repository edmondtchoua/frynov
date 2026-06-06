<?php

namespace App\Modules\Orders\Tests\Integration;

use App\Models\User;
use App\Modules\Billing\Models\Plan;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\Stock;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderReturn;
use App\Modules\Orders\Services\OrderReturnService;
use App\Modules\Tenants\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OrderReturnTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $admin;
    private string $token;
    private Order $order;
    private \App\Modules\Orders\Models\OrderLine $line;
    private Stock $stock;
    private OrderReturnService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'admin',  'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'member', 'guard_name' => 'web']);
        Plan::firstOrCreate(['code' => Plan::CODE_STARTER], [
            'name' => 'Starter', 'price_monthly_cents' => 0, 'price_yearly_cents' => 0,
            'currency' => 'XOF', 'trial_days' => 14, 'is_active' => true,
            'is_public' => true, 'sort_order' => 1,
        ]);

        $this->tenant = Tenant::create([
            'name' => 'Ret', 'slug' => 'ret-t', 'plan' => 'starter', 'status' => 'active',
            'settings' => ['currency' => 'XOF', 'timezone' => 'Africa/Dakar', 'locale' => 'fr'],
        ]);
        $this->admin = User::create([
            'name' => 'A', 'email' => 'a@ret.sn',
            'password' => bcrypt('x'), 'tenant_id' => $this->tenant->id,
        ]);
        $this->admin->assignTenantRole('admin');
        $this->token = $this->admin->createToken('api')->plainTextToken;

        $wh = Warehouse::create([
            'tenant_id' => $this->tenant->id, 'name' => 'WH', 'code' => 'WH-R', 'is_default' => true,
        ]);
        $product = Product::withoutTenantScope()->create([
            'tenant_id' => $this->tenant->id, 'sku' => 'RET-001', 'name' => 'Produit Test',
            'price_amount' => 10000, 'price_currency' => 'XOF',
            'status' => 'active', 'has_variants' => false,
        ]);
        $this->stock = Stock::create([
            'tenant_id' => $this->tenant->id, 'warehouse_id' => $wh->id,
            'product_id' => $product->id, 'quantity' => 10,
            'reserved_quantity' => 0, 'low_stock_threshold' => 2,
            'unit_cost_cents' => 8000, 'total_value_cents' => 80000,
        ]);
        $this->order = Order::withoutTenantScope()->create([
            'tenant_id' => $this->tenant->id, 'number' => 'ORD-001',
            'status' => 'fulfilled', 'currency' => 'XOF',
            'subtotal_cents' => 20000, 'tax_cents' => 0,
            'total_cents' => 20000, 'discount_cents' => 0,
        ]);
        $this->line = $this->order->lines()->create([
            'tenant_id'       => $this->tenant->id,
            'product_id'      => $product->id,
            'variant_id'      => null,
            'sku'             => 'RET-001',
            'name'            => 'Produit Test',
            'quantity'        => 2,
            'unit_price_cents' => 10000,
        ]);
        $this->svc = app(OrderReturnService::class);
    }

    #[Test]
    public function can_create_return_request(): void
    {
        $return = $this->svc->create(
            $this->order,
            [['order_line_id' => $this->line->id, 'quantity' => 1, 'condition' => 'resalable']],
            OrderReturn::REASON_DEFECTIVE,
            $this->admin->id,
            'Article cassé à la réception',
        );

        $this->assertSame(OrderReturn::STATUS_PENDING, $return->status);
        $this->assertStringStartsWith('RET-', $return->number);
        $this->assertCount(1, $return->lines);
        $this->assertSame(1, $return->lines->first()->quantity_requested);
    }

    #[Test]
    public function full_lifecycle_approve_and_restock(): void
    {
        $before = $this->stock->fresh()->quantity;

        $return = $this->svc->create(
            $this->order,
            [['order_line_id' => $this->line->id, 'quantity' => 2, 'condition' => 'resalable']],
            OrderReturn::REASON_WRONG_ITEM,
            $this->admin->id,
        );
        $this->svc->approve($return, $this->admin->id);
        $this->assertSame(OrderReturn::STATUS_APPROVED, $return->fresh()->status);

        $this->svc->restock($return, $this->admin->id);
        $this->assertSame(OrderReturn::STATUS_RESTOCKED, $return->fresh()->status);
        $this->assertSame($before + 2, $this->stock->fresh()->quantity);
        $this->assertDatabaseHas('stock_movements', [
            'reason'    => 'return',
            'reference' => $return->number,
        ]);
    }

    #[Test]
    public function damaged_items_not_restocked(): void
    {
        $before = $this->stock->fresh()->quantity;

        $return = $this->svc->create(
            $this->order,
            [['order_line_id' => $this->line->id, 'quantity' => 1, 'condition' => 'damaged']],
            OrderReturn::REASON_DAMAGED,
            $this->admin->id,
        );
        $this->svc->approve($return, $this->admin->id);
        $this->svc->restock($return, $this->admin->id);

        // Stock unchanged — damaged item not restocked
        $this->assertSame($before, $this->stock->fresh()->quantity);
    }

    #[Test]
    public function can_reject_return(): void
    {
        $return = $this->svc->create(
            $this->order,
            [['order_line_id' => $this->line->id, 'quantity' => 1, 'condition' => 'resalable']],
            OrderReturn::REASON_CHANGED_MIND,
            $this->admin->id,
        );
        $this->svc->reject($return, $this->admin->id, 'Délai de retour dépassé');

        $this->assertSame(OrderReturn::STATUS_REJECTED, $return->fresh()->status);
        $this->assertSame('Délai de retour dépassé', $return->fresh()->rejection_reason);
    }

    #[Test]
    public function cannot_return_on_cancelled_order(): void
    {
        $cancelled = Order::withoutTenantScope()->create([
            'tenant_id' => $this->tenant->id, 'number' => 'ORD-002',
            'status' => 'cancelled', 'currency' => 'XOF',
            'subtotal_cents' => 0, 'tax_cents' => 0, 'total_cents' => 0, 'discount_cents' => 0,
        ]);

        $this->expectException(\DomainException::class);
        $this->svc->create($cancelled, [], 'other', $this->admin->id);
    }

    #[Test]
    public function api_create_and_list_returns(): void
    {
        $resp = $this->withToken($this->token)
            ->postJson("/api/orders/{$this->order->id}/returns", [
                'reason'     => 'defective',
                'resolution' => 'refund',
                'lines'      => [['order_line_id' => $this->line->id, 'quantity' => 1, 'condition' => 'resalable']],
            ])->assertStatus(201);

        $this->assertStringStartsWith('RET-', $resp->json('data.number'));

        $this->withToken($this->token)
            ->getJson('/api/orders/returns?status=pending')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    #[Test]
    public function api_approve_return(): void
    {
        $return = $this->svc->create(
            $this->order,
            [['order_line_id' => $this->line->id, 'quantity' => 1, 'condition' => 'resalable']],
            'other', $this->admin->id,
        );

        $this->withToken($this->token)
            ->postJson("/api/orders/returns/{$return->id}/approve", ['internal_note' => 'OK validé'])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');
    }
}
