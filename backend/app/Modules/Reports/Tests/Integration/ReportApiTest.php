<?php

namespace App\Modules\Reports\Tests\Integration;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Services\StockService;
use App\Modules\Orders\Services\OrderService;
use App\Modules\Payments\Models\Payment;
use App\Modules\Tenants\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReportApiTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User   $user;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::create(['name' => 'Boutique', 'slug' => 'boutique', 'plan' => 'starter', 'status' => 'active']);
        $this->user   = User::create([
            'name'      => 'Admin',
            'email'     => 'admin@test.com',
            'password'  => Hash::make('Secret123!'),
            'tenant_id' => $this->tenant->id,
        ]);
        $this->token  = $this->user->createToken('api')->plainTextToken;
    }

    private function auth(): array
    {
        return ['Authorization' => "Bearer {$this->token}"];
    }

    // ── Auth guard ────────────────────────────────────────────────────────────

    #[Test]
    public function all_endpoints_require_authentication(): void
    {
        $this->getJson('/api/reports/dashboard')->assertUnauthorized();
        $this->getJson('/api/reports/sales')->assertUnauthorized();
        $this->getJson('/api/reports/stock')->assertUnauthorized();
    }

    // ── Dashboard ─────────────────────────────────────────────────────────────

    #[Test]
    public function dashboard_returns_200_with_correct_structure(): void
    {
        $this->getJson('/api/reports/dashboard', $this->auth())
            ->assertOk()
            ->assertJsonStructure([
                'kpis'          => ['revenue_today', 'revenue_today_change', 'orders_today', 'orders_today_change', 'active_products', 'low_stock_alerts'],
                'revenue_chart',
                'recent_orders',
                'top_products',
            ]);
    }

    #[Test]
    public function dashboard_kpis_are_zero_with_no_data(): void
    {
        $this->getJson('/api/reports/dashboard', $this->auth())
            ->assertOk()
            ->assertJsonPath('kpis.revenue_today',  0)
            ->assertJsonPath('kpis.orders_today',   0)
            ->assertJsonPath('kpis.active_products', 0);
    }

    #[Test]
    public function dashboard_counts_todays_revenue(): void
    {
        Payment::create([
            'tenant_id'    => $this->tenant->id,
            'amount_cents' => 85_000,
            'currency'     => 'EUR',
            'method'       => 'cash',
            'paid_at'      => now(),
            'performed_by' => $this->user->id,
        ]);

        $this->getJson('/api/reports/dashboard', $this->auth())
            ->assertOk()
            ->assertJsonPath('kpis.revenue_today', 85_000);
    }

    #[Test]
    public function dashboard_revenue_chart_has_7_points(): void
    {
        $this->getJson('/api/reports/dashboard', $this->auth())
            ->assertOk()
            ->assertJsonCount(7, 'revenue_chart');
    }

    #[Test]
    public function dashboard_tenant_isolation(): void
    {
        $other = Tenant::create(['name' => 'Other', 'slug' => 'other', 'plan' => 'starter', 'status' => 'active']);
        Payment::create(['tenant_id' => $other->id, 'amount_cents' => 999_000, 'currency' => 'EUR', 'method' => 'cash', 'paid_at' => now()]);

        $this->getJson('/api/reports/dashboard', $this->auth())
            ->assertOk()
            ->assertJsonPath('kpis.revenue_today', 0);
    }

    // ── Sales ─────────────────────────────────────────────────────────────────

    #[Test]
    public function sales_defaults_to_7d(): void
    {
        $this->getJson('/api/reports/sales', $this->auth())
            ->assertOk()
            ->assertJsonPath('period', '7d')
            ->assertJsonCount(7, 'revenue_chart');
    }

    #[Test]
    public function sales_accepts_30d_period(): void
    {
        $this->getJson('/api/reports/sales?period=30d', $this->auth())
            ->assertOk()
            ->assertJsonPath('period', '30d')
            ->assertJsonCount(30, 'revenue_chart');
    }

    #[Test]
    public function sales_invalid_period_falls_back_to_7d(): void
    {
        $this->getJson('/api/reports/sales?period=bad', $this->auth())
            ->assertOk()
            ->assertJsonPath('period', '7d');
    }

    #[Test]
    public function sales_totals_aggregate_payments(): void
    {
        Payment::create(['tenant_id' => $this->tenant->id, 'amount_cents' => 20_000, 'currency' => 'EUR', 'method' => 'cash', 'paid_at' => now(), 'performed_by' => $this->user->id]);
        Payment::create(['tenant_id' => $this->tenant->id, 'amount_cents' => 35_000, 'currency' => 'EUR', 'method' => 'card', 'paid_at' => now(), 'performed_by' => $this->user->id]);

        $this->getJson('/api/reports/sales?period=7d', $this->auth())
            ->assertOk()
            ->assertJsonPath('total_revenue', 55_000)
            ->assertJsonPath('total_orders',  2);
    }

    #[Test]
    public function sales_returns_by_method_breakdown(): void
    {
        Payment::create(['tenant_id' => $this->tenant->id, 'amount_cents' => 10_000, 'currency' => 'EUR', 'method' => 'cash', 'paid_at' => now()]);
        Payment::create(['tenant_id' => $this->tenant->id, 'amount_cents' => 25_000, 'currency' => 'EUR', 'method' => 'mobile_money', 'paid_at' => now()]);

        $res = $this->getJson('/api/reports/sales', $this->auth())->assertOk();
        $this->assertNotEmpty($res->json('by_method'));
    }

    // ── Stock ─────────────────────────────────────────────────────────────────

    #[Test]
    public function stock_returns_200_with_correct_structure(): void
    {
        $this->getJson('/api/reports/stock', $this->auth())
            ->assertOk()
            ->assertJsonStructure(['stock_value', 'total_skus', 'out_of_stock', 'low_stock_count', 'low_stock_items', 'recent_movements']);
    }

    #[Test]
    public function stock_value_equals_quantity_times_cost(): void
    {
        /** @var StockService $stockSvc */
        $stockSvc = $this->app->make(StockService::class);

        $product = Product::create([
            'tenant_id'      => $this->tenant->id,
            'sku'            => 'PRD-001',
            'name'           => 'Tissu',
            'price_amount'   => 10_000,
            'cost_amount'    => 6_000,
            'price_currency' => 'EUR',
            'status'         => 'active',
        ]);

        $stock = $stockSvc->findOrCreate($this->tenant->id, $product->id, null);
        $stockSvc->moveIn($stock, 10);  // 10 × 6000 = 60 000

        $this->getJson('/api/reports/stock', $this->auth())
            ->assertOk()
            ->assertJsonPath('stock_value', 60_000)
            ->assertJsonPath('total_skus',  1);
    }
}
