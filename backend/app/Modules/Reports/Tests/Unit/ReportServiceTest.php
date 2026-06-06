<?php

namespace App\Modules\Reports\Tests\Unit;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\Stock;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Services\StockService;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Services\OrderService;
use App\Modules\Payments\Models\Payment;
use App\Modules\Reports\Services\ReportService;
use App\Modules\Tenants\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReportServiceTest extends TestCase
{
    use RefreshDatabase;

    private ReportService $service;
    private Tenant $tenant;
    private User   $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ReportService();
        $this->tenant  = Tenant::create(['name' => 'Test', 'slug' => 'test', 'plan' => 'starter', 'status' => 'active']);
        $this->user    = User::create([
            'name'      => 'U',
            'email'     => 'u@test.com',
            'password'  => Hash::make('pass'),
            'tenant_id' => $this->tenant->id,
        ]);
    }

    #[Test]
    public function dashboard_returns_required_structure(): void
    {
        $data = $this->service->dashboard($this->tenant->id);

        $this->assertArrayHasKey('kpis',          $data);
        $this->assertArrayHasKey('revenue_chart', $data);
        $this->assertArrayHasKey('recent_orders', $data);
        $this->assertArrayHasKey('top_products',  $data);

        foreach (['revenue_today', 'revenue_today_change', 'orders_today', 'orders_today_change', 'active_products', 'low_stock_alerts'] as $key) {
            $this->assertArrayHasKey($key, $data['kpis']);
        }
    }

    #[Test]
    public function dashboard_kpis_are_zero_for_empty_tenant(): void
    {
        $data = $this->service->dashboard($this->tenant->id);

        $this->assertSame(0,    $data['kpis']['revenue_today']);
        $this->assertSame(0,    $data['kpis']['orders_today']);
        $this->assertSame(0,    $data['kpis']['active_products']);
        $this->assertSame(0,    $data['kpis']['low_stock_alerts']);
        $this->assertNull(      $data['kpis']['revenue_today_change']);
        $this->assertNull(      $data['kpis']['orders_today_change']);
    }

    #[Test]
    public function dashboard_revenue_chart_has_7_points(): void
    {
        $chart = $this->service->dashboard($this->tenant->id)['revenue_chart'];
        $this->assertCount(7, $chart);
        foreach ($chart as $point) {
            $this->assertArrayHasKey('date',   $point);
            $this->assertArrayHasKey('amount', $point);
            $this->assertArrayHasKey('count',  $point);
        }
    }

    #[Test]
    public function dashboard_counts_todays_payments(): void
    {
        Payment::create([
            'tenant_id'    => $this->tenant->id,
            'amount_cents' => 75_000,
            'currency'     => 'EUR',
            'method'       => 'cash',
            'paid_at'      => now(),
            'performed_by' => $this->user->id,
        ]);

        $data = $this->service->dashboard($this->tenant->id);
        $this->assertSame(75_000, $data['kpis']['revenue_today']);
    }

    #[Test]
    public function dashboard_excludes_other_tenant_data(): void
    {
        $other = Tenant::create(['name' => 'Other', 'slug' => 'other', 'plan' => 'starter', 'status' => 'active']);

        Payment::create([
            'tenant_id'    => $other->id,
            'amount_cents' => 100_000,
            'currency'     => 'EUR',
            'method'       => 'cash',
            'paid_at'      => now(),
            'performed_by' => $this->user->id,
        ]);

        $data = $this->service->dashboard($this->tenant->id);
        $this->assertSame(0, $data['kpis']['revenue_today']);
    }

    #[Test]
    public function dashboard_counts_active_products(): void
    {
        Product::create(['tenant_id' => $this->tenant->id, 'sku' => 'A', 'name' => 'Active', 'price_amount' => 10000, 'price_currency' => 'EUR', 'status' => 'active']);
        Product::create(['tenant_id' => $this->tenant->id, 'sku' => 'B', 'name' => 'Draft',  'price_amount' => 10000, 'price_currency' => 'EUR', 'status' => 'draft']);

        $data = $this->service->dashboard($this->tenant->id);
        $this->assertSame(1, $data['kpis']['active_products']);
    }

    #[Test]
    public function sales_returns_correct_period_and_chart_size(): void
    {
        foreach (['7d' => 7, '30d' => 30, '90d' => 90] as $period => $days) {
            $data = $this->service->sales($this->tenant->id, $period);
            $this->assertSame($period, $data['period']);
            $this->assertCount($days, $data['revenue_chart']);
        }
    }

    #[Test]
    public function sales_totals_match_payments(): void
    {
        Payment::create(['tenant_id' => $this->tenant->id, 'amount_cents' => 30_000, 'currency' => 'EUR', 'method' => 'cash', 'paid_at' => now()]);
        Payment::create(['tenant_id' => $this->tenant->id, 'amount_cents' => 20_000, 'currency' => 'EUR', 'method' => 'card', 'paid_at' => now()]);

        $data = $this->service->sales($this->tenant->id, '7d');
        $this->assertSame(50_000, $data['total_revenue']);
        $this->assertSame(2,      $data['total_orders']);
    }

    #[Test]
    public function stock_returns_required_structure(): void
    {
        $data = $this->service->stock($this->tenant->id);

        foreach (['stock_value', 'total_skus', 'out_of_stock', 'low_stock_count', 'low_stock_items', 'recent_movements'] as $key) {
            $this->assertArrayHasKey($key, $data);
        }
        $this->assertSame(0, $data['stock_value']);
        $this->assertSame(0, $data['total_skus']);
    }

    #[Test]
    public function sales_revenue_is_scoped_to_a_warehouse(): void
    {
        // Sprint 20 multi-sites: report revenue per site.
        $whA = Warehouse::create(['tenant_id' => $this->tenant->id, 'name' => 'A', 'code' => 'WH-A', 'is_default' => true]);
        $whB = Warehouse::create(['tenant_id' => $this->tenant->id, 'name' => 'B', 'code' => 'WH-B', 'is_default' => false]);

        $this->paymentInWarehouse(30_000, $whA->id);
        $this->paymentInWarehouse(20_000, $whB->id);

        $this->assertSame(30_000, $this->service->sales($this->tenant->id, '7d', $whA->id)['total_revenue']);
        $this->assertSame(50_000, $this->service->sales($this->tenant->id, '7d')['total_revenue']);
    }

    #[Test]
    public function stock_value_is_scoped_to_a_warehouse(): void
    {
        $whA = Warehouse::create(['tenant_id' => $this->tenant->id, 'name' => 'A', 'code' => 'WH-A', 'is_default' => true]);
        $whB = Warehouse::create(['tenant_id' => $this->tenant->id, 'name' => 'B', 'code' => 'WH-B', 'is_default' => false]);
        // price_amount = unit value (no cost_amount → COALESCE falls back to price), so value is deterministic.
        $product = Product::create(['tenant_id' => $this->tenant->id, 'sku' => 'P1', 'name' => 'P', 'price_amount' => 8000, 'price_currency' => 'EUR', 'status' => 'active']);

        Stock::create(['tenant_id' => $this->tenant->id, 'warehouse_id' => $whA->id, 'product_id' => $product->id, 'quantity' => 5, 'reserved_quantity' => 0, 'low_stock_threshold' => 0, 'unit_cost_cents' => 8000, 'total_value_cents' => 40000]);
        Stock::create(['tenant_id' => $this->tenant->id, 'warehouse_id' => $whB->id, 'product_id' => $product->id, 'quantity' => 3, 'reserved_quantity' => 0, 'low_stock_threshold' => 0, 'unit_cost_cents' => 8000, 'total_value_cents' => 24000]);

        $a = $this->service->stock($this->tenant->id, $whA->id);
        $this->assertSame(1, $a['total_skus']);
        $this->assertSame(5 * 8000, $a['stock_value']);

        $all = $this->service->stock($this->tenant->id);
        $this->assertSame(2, $all['total_skus']);
        $this->assertSame((5 + 3) * 8000, $all['stock_value']);
    }

    private function paymentInWarehouse(int $cents, string $warehouseId): void
    {
        $p = Payment::create(['tenant_id' => $this->tenant->id, 'amount_cents' => $cents, 'currency' => 'EUR', 'method' => 'cash', 'paid_at' => now()]);
        $p->warehouse_id = $warehouseId;
        $p->save();
    }
}
