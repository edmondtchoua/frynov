<?php

namespace App\Modules\Inventory\Tests\Unit;

use App\Models\User;
use App\Modules\Billing\Models\Plan;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\Stock;
use App\Modules\Inventory\Models\StockAdjustmentRequest;
use App\Modules\Inventory\Services\StockAdjustmentService;
use App\Modules\Inventory\Services\StockService;
use App\Modules\Tenants\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StockAdjustmentServiceTest extends TestCase
{
    use RefreshDatabase;

    private StockAdjustmentService $svc;
    private Tenant $tenant;
    private User   $admin;
    private Stock  $stock;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $this->tenant = Tenant::create([
            'name' => 'Test', 'slug' => 'test', 'plan' => 'starter',
            'status' => 'active',
            'settings' => ['adjustment_approval_threshold' => 10_000_00], // 10 000 XOF
        ]);

        $this->admin = User::create([
            'name' => 'Admin', 'email' => 'admin@test.sn',
            'password' => bcrypt('x'), 'tenant_id' => $this->tenant->id,
        ]);
        $this->admin->assignTenantRole('admin');

        $product = Product::create([
            'tenant_id' => $this->tenant->id, 'sku' => 'PRD-0001',
            'name' => 'Produit Test', 'price_amount' => 5000_00,
            'price_currency' => 'XOF', 'status' => 'active', 'has_variants' => false,
        ]);

        $this->stock = Stock::create([
            'tenant_id' => $this->tenant->id, 'product_id' => $product->id,
            'quantity' => 100, 'reserved_quantity' => 0,
            'low_stock_threshold' => 10, 'unit_cost_cents' => 3000_00,
            'total_value_cents' => 100 * 3000_00,
        ]);

        $this->svc = new StockAdjustmentService(app(StockService::class));
    }

    #[Test]
    public function small_adjustment_is_executed_immediately_without_approval(): void
    {
        // delta = -1 unit × 30 000 = 30 000 centimes < threshold 1 000 000
        $req = $this->svc->request($this->stock, 99, 'loss', 'One unit lost', $this->admin);

        $this->assertSame(StockAdjustmentRequest::STATUS_EXECUTED, $req->fresh()->status);
        $this->assertSame(99, $this->stock->fresh()->quantity);
    }

    #[Test]
    public function large_adjustment_creates_pending_request_requiring_approval(): void
    {
        // delta = -50 units × 30 000 = 1 500 000 centimes > threshold 1 000 000
        $req = $this->svc->request($this->stock, 50, 'theft', 'Major theft', $this->admin);

        $this->assertSame(StockAdjustmentRequest::STATUS_PENDING, $req->fresh()->status);
        // Stock NOT yet changed
        $this->assertSame(100, $this->stock->fresh()->quantity);
    }

    #[Test]
    public function admin_can_approve_pending_request(): void
    {
        $req = $this->svc->request($this->stock, 50, 'theft', null, $this->admin);
        $this->assertSame(StockAdjustmentRequest::STATUS_PENDING, $req->fresh()->status);

        $this->svc->approve($req->fresh(), $this->admin);

        $this->assertSame(StockAdjustmentRequest::STATUS_EXECUTED, $req->fresh()->status);
        $this->assertSame(50, $this->stock->fresh()->quantity);
        $this->assertSame($this->admin->id, $req->fresh()->reviewed_by);
    }

    #[Test]
    public function admin_can_reject_pending_request(): void
    {
        $req = $this->svc->request($this->stock, 50, 'theft', null, $this->admin);

        $this->svc->reject($req->fresh(), $this->admin, 'Insufficient evidence');

        $rejectedReq = $req->fresh();
        $this->assertSame(StockAdjustmentRequest::STATUS_REJECTED, $rejectedReq->status);
        $this->assertSame('Insufficient evidence', $rejectedReq->rejection_reason);
        // Stock unchanged
        $this->assertSame(100, $this->stock->fresh()->quantity);
    }

    #[Test]
    public function cannot_approve_already_executed_request(): void
    {
        // Small adjustment → auto-executed
        $req = $this->svc->request($this->stock, 99, 'loss', null, $this->admin);
        $this->assertSame(StockAdjustmentRequest::STATUS_EXECUTED, $req->fresh()->status);

        $this->expectException(\DomainException::class);
        $this->svc->approve($req->fresh(), $this->admin);
    }

    #[Test]
    public function invalid_reason_throws_exception(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->svc->request($this->stock, 90, 'invalid-reason', null, $this->admin);
    }

    #[Test]
    public function value_cents_is_correctly_computed(): void
    {
        // |delta| = |50 - 100| = 50 units × 30 000 = 1 500 000
        $req = $this->svc->request($this->stock, 50, 'theft', null, $this->admin);
        $this->assertSame(50 * 3000_00, $req->value_cents);
    }
}
