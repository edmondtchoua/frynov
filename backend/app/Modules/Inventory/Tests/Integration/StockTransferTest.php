<?php
namespace App\Modules\Inventory\Tests\Integration;

use App\Models\User;
use App\Modules\Billing\Models\Plan;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\Stock;
use App\Modules\Inventory\Models\StockTransfer;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Services\StockTransferService;
use App\Modules\Tenants\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StockTransferTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $admin;
    private string $token;
    private Warehouse $whA, $whB;
    private Product $product;
    private Stock $stockA;
    private StockTransferService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Plan::firstOrCreate(['code' => Plan::CODE_STARTER], [
            'name' => 'Starter', 'price_monthly_cents' => 0, 'price_yearly_cents' => 0,
            'currency' => 'XOF', 'trial_days' => 14, 'is_active' => true, 'is_public' => true, 'sort_order' => 1,
        ]);
        $this->tenant = Tenant::create(['name' => 'T2', 'slug' => 't-transfer', 'plan' => 'starter', 'status' => 'active', 'settings' => ['currency' => 'XOF', 'timezone' => 'Africa/Dakar', 'locale' => 'fr']]);
        $this->admin  = User::create(['name' => 'A', 'email' => 'a@transfer.sn', 'password' => bcrypt('x'), 'tenant_id' => $this->tenant->id]);
        $this->admin->assignTenantRole('admin');
        $this->token  = $this->admin->createToken('api')->plainTextToken;
        $this->whA    = Warehouse::create(['tenant_id' => $this->tenant->id, 'name' => 'WH-A', 'code' => 'WH-A', 'is_default' => true]);
        $this->whB    = Warehouse::create(['tenant_id' => $this->tenant->id, 'name' => 'WH-B', 'code' => 'WH-B', 'is_default' => false]);
        $this->product = Product::withoutTenantScope()->create(['tenant_id' => $this->tenant->id, 'sku' => 'TRF-001', 'name' => 'P', 'price_amount' => 5000, 'price_currency' => 'XOF', 'status' => 'active', 'has_variants' => false]);
        $this->stockA  = Stock::create(['tenant_id' => $this->tenant->id, 'warehouse_id' => $this->whA->id, 'product_id' => $this->product->id, 'quantity' => 100, 'reserved_quantity' => 0, 'low_stock_threshold' => 5, 'unit_cost_cents' => 5000, 'total_value_cents' => 500000]);
        $this->svc = app(StockTransferService::class);
    }

    #[Test]
    public function full_transfer_lifecycle_no_black_hole(): void
    {
        $transfer = $this->svc->create($this->tenant->id, $this->whA->id, $this->whB->id,
            [['product_id' => $this->product->id, 'quantity' => 30]], $this->admin->id);

        $this->assertSame('draft', $transfer->status);

        // Ship
        $this->svc->ship($transfer, $this->admin->id);
        $this->assertSame(70, $this->stockA->fresh()->quantity);
        $this->assertSame('in_transit', $transfer->fresh()->status);

        // Receive (all 30)
        $this->svc->receive($transfer->fresh(), $this->admin->id, [$transfer->lines->first()->id => 30]);
        $stockB = Stock::where(['warehouse_id' => $this->whB->id, 'product_id' => $this->product->id])->first();
        $this->assertSame(30, $stockB->quantity);
        $this->assertSame('received', $transfer->fresh()->status);
    }

    #[Test]
    public function partial_reception_creates_dispute_and_write_off_resolves(): void
    {
        $transfer = $this->svc->create($this->tenant->id, $this->whA->id, $this->whB->id,
            [['product_id' => $this->product->id, 'quantity' => 10]], $this->admin->id);
        $this->svc->ship($transfer, $this->admin->id);

        // Only 8 received out of 10
        $this->svc->receive($transfer->fresh(), $this->admin->id, [$transfer->lines->first()->id => 8]);
        $this->assertSame('partial', $transfer->fresh()->status);
        $this->assertSame(2, $transfer->fresh()->lines->first()->quantity_discrepancy);

        // Resolve as write-off
        $this->svc->resolveDispute($transfer->fresh(), $this->admin->id, 'write_off', 'Colis endommagé');
        $this->assertSame('completed', $transfer->fresh()->status);
        $this->assertDatabaseHas('stock_movements', ['reason' => 'write_off', 'reference' => $transfer->number]);
    }

    #[Test]
    public function cannot_receive_more_than_shipped(): void
    {
        // Receiving more than was shipped would materialize phantom stock.
        $transfer = $this->svc->create($this->tenant->id, $this->whA->id, $this->whB->id,
            [['product_id' => $this->product->id, 'quantity' => 10]], $this->admin->id);
        $this->svc->ship($transfer, $this->admin->id);

        $this->expectException(\DomainException::class);
        // Shipped 10, operator types 15 → must be rejected
        $this->svc->receive($transfer->fresh(), $this->admin->id, [$transfer->lines->first()->id => 15]);
    }

    #[Test]
    public function cross_tenant_transfer_is_blocked(): void
    {
        $otherTenant = Tenant::create(['name' => 'Other', 'slug' => 'other-t', 'plan' => 'starter', 'status' => 'active', 'settings' => []]);
        $otherWh     = Warehouse::create(['tenant_id' => $otherTenant->id, 'name' => 'OWH', 'code' => 'OWH', 'is_default' => true]);

        $this->expectException(\DomainException::class);
        $this->svc->create($this->tenant->id, $this->whA->id, $otherWh->id, [], $this->admin->id);
    }

    #[Test]
    public function cannot_ship_insufficient_stock(): void
    {
        $transfer = $this->svc->create($this->tenant->id, $this->whA->id, $this->whB->id,
            [['product_id' => $this->product->id, 'quantity' => 200]], $this->admin->id); // > 100 available

        $this->expectException(\App\Modules\Inventory\Exceptions\InsufficientStockException::class);
        $this->svc->ship($transfer, $this->admin->id);
    }

    #[Test]
    public function same_warehouse_transfer_is_blocked(): void
    {
        $this->expectException(\DomainException::class);
        $this->svc->create($this->tenant->id, $this->whA->id, $this->whA->id, [], $this->admin->id);
    }

    #[Test]
    public function transfer_api_create_and_ship(): void
    {
        $resp = $this->withToken($this->token)->postJson('/api/inventory/transfers', [
            'source_warehouse_id'      => $this->whA->id,
            'destination_warehouse_id' => $this->whB->id,
            'lines' => [['product_id' => $this->product->id, 'quantity' => 20]],
        ])->assertStatus(201);

        $id = $resp->json('data.id');

        $this->withToken($this->token)
            ->postJson("/api/inventory/transfers/{$id}/ship", [])
            ->assertOk()->assertJsonPath('data.status', 'in_transit');
    }
}
