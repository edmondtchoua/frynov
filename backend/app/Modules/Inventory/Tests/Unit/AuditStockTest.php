<?php
namespace App\Modules\Inventory\Tests\Unit;

use App\Models\User;
use App\Modules\Billing\Models\Plan;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\Stock;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Services\StockService;
use App\Modules\Platform\Models\AuditLog;
use App\Modules\Tenants\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuditStockTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Stock $stock;
    private StockService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Plan::firstOrCreate(['code' => 'starter'], ['name' => 'Starter', 'price_monthly_cents' => 0, 'price_yearly_cents' => 0, 'currency' => 'XOF', 'trial_days' => 14, 'is_active' => true, 'is_public' => true, 'sort_order' => 1]);
        $this->tenant = Tenant::create(['name' => 'T', 'slug' => 'audit-stock', 'plan' => 'starter', 'status' => 'active', 'settings' => []]);
        $wh = Warehouse::create(['tenant_id' => $this->tenant->id, 'name' => 'WH', 'code' => 'WH-AS', 'is_default' => true]);
        $p  = Product::withoutTenantScope()->create(['tenant_id' => $this->tenant->id, 'sku' => 'AUD-001', 'name' => 'P', 'price_amount' => 1000, 'price_currency' => 'XOF', 'status' => 'active', 'has_variants' => false]);
        $this->stock = Stock::create(['tenant_id' => $this->tenant->id, 'warehouse_id' => $wh->id, 'product_id' => $p->id, 'quantity' => 10, 'reserved_quantity' => 0, 'low_stock_threshold' => 2, 'unit_cost_cents' => 500, 'total_value_cents' => 5000]);
        $this->svc = app(StockService::class);
    }

    #[Test]
    public function move_in_creates_audit_log(): void
    {
        $this->svc->moveIn($this->stock, 5, 'delivery', null, null, null, 500);
        $this->assertDatabaseHas('audit_logs', ['action' => 'stock.moved_in', 'tenant_id' => $this->tenant->id]);
    }

    #[Test]
    public function adjust_creates_audit_log(): void
    {
        $this->svc->adjust($this->stock, 8, 'count', 'Inventaire test', null);
        $this->assertDatabaseHas('audit_logs', ['action' => 'stock.adjusted', 'tenant_id' => $this->tenant->id]);
    }
}
