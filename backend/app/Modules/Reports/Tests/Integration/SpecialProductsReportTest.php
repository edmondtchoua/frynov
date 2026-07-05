<?php

namespace App\Modules\Reports\Tests\Integration;

use App\Models\User;
use App\Modules\Billing\Models\Plan;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Services\InventoryUnitService;
use App\Modules\Inventory\Services\StockService;
use App\Modules\Orders\Services\OrderService;
use App\Modules\Tenants\Models\Tenant;
use App\Modules\Warranties\Models\WarrantyContract;
use App\Modules\Warranties\Models\WarrantyPolicy;
use App\Modules\Warranties\Services\WarrantyClaimService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * RC-5G — reporting produits spéciaux : valorisation (agrégé + sérialisé par unité), exclusion des
 * services/digital, rappel garanties/SAV/digital.
 */
class SpecialProductsReportTest extends TestCase
{
    use RefreshDatabase;

    private const CUSTOMER_ID = '55555555-5555-4555-8555-555555555555';

    private Tenant $tenant;
    private User $user;
    private string $token;
    private Warehouse $wh;
    private OrderService $orders;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        Plan::firstOrCreate(['code' => 'starter'], ['name' => 'Starter', 'price_monthly_cents' => 0, 'price_yearly_cents' => 0, 'currency' => 'XOF', 'trial_days' => 14, 'is_active' => true, 'is_public' => true, 'sort_order' => 1]);

        $this->tenant = Tenant::create(['name' => 'Rep', 'slug' => 'rep-test', 'plan' => 'starter', 'status' => 'active', 'settings' => []]);
        $this->user = User::create(['name' => 'M', 'email' => 'm@rep.sn', 'password' => Hash::make('x'), 'tenant_id' => $this->tenant->id]);
        $this->user->assignTenantRole('manager');
        $this->token = $this->user->createToken('api')->plainTextToken;

        $this->wh = Warehouse::create(['tenant_id' => $this->tenant->id, 'name' => 'WH', 'code' => 'WH-REP', 'is_default' => true]);
        $this->orders = $this->app->make(OrderService::class);
    }

    private function auth(): array
    {
        return ['Authorization' => "Bearer {$this->token}"];
    }

    private function sell(Product $p, int $qty = 1): \App\Modules\Orders\Models\Order
    {
        $order = $this->orders->create(['customer_id' => self::CUSTOMER_ID, 'items' => [['product_id' => $p->id, 'quantity' => $qty]]], $this->tenant->id, $this->user->id);
        $order = $this->orders->confirm($order, $this->user->id);

        return $this->orders->fulfill($order, $this->user->id);
    }

    /** Met en place un mix : agrégé + sérialisé (1 vendu / 2 en stock) + digital + service + garantie/SAV. */
    private function seedMix(): void
    {
        // Agrégé : 5 unités à coût 1000 → valeur 5000.
        $mug = Product::create(['tenant_id' => $this->tenant->id, 'sku' => 'MUG', 'name' => 'Mug', 'price_amount' => 3000, 'cost_amount' => 1000, 'price_currency' => 'XOF', 'status' => 'active', 'product_type' => Product::TYPE_SIMPLE]);
        $stock = $this->app->make(StockService::class)->findOrCreate($this->tenant->id, $mug->id, null);
        $this->app->make(StockService::class)->moveIn($stock, 5, \App\Modules\Inventory\Models\StockMovement::REASON_DELIVERY, null, null, $this->user->id, 1000);

        // Sérialisé sous garantie : coût 500000, 3 unités reçues, 1 vendue.
        $policy = WarrantyPolicy::create(['tenant_id' => $this->tenant->id, 'name' => 'G12', 'duration_months' => 12, 'is_active' => true]);
        $phone = Product::create(['tenant_id' => $this->tenant->id, 'sku' => 'IPHONE', 'name' => 'iPhone', 'price_amount' => 800000, 'cost_amount' => 500000, 'price_currency' => 'XOF', 'status' => 'active', 'product_type' => Product::TYPE_SIMPLE, 'stock_tracking' => Product::STOCK_TRACKING_SERIALIZED, 'warranty_policy_id' => $policy->id]);
        $this->app->make(InventoryUnitService::class)->registerMany($this->tenant->id, $phone->id, [
            ['serial_type' => 'imei', 'serial_value' => '359000000000001', 'warehouse_id' => $this->wh->id, 'unit_cost_cents' => 500000],
            ['serial_type' => 'imei', 'serial_value' => '359000000000002', 'warehouse_id' => $this->wh->id, 'unit_cost_cents' => 500000],
            ['serial_type' => 'imei', 'serial_value' => '359000000000003', 'warehouse_id' => $this->wh->id, 'unit_cost_cents' => 500000],
        ], $this->user->id);
        $phoneOrder = $this->sell($phone, 1); // 1 vendu → contrat de garantie actif

        // SAV : ouvrir une réclamation sur le contrat généré.
        $contract = WarrantyContract::withoutTenantScope()->where('order_id', $phoneOrder->id)->firstOrFail();
        $this->app->make(WarrantyClaimService::class)->open($contract, ['reason' => 'defect'], $this->user->id);

        // Digital : vendu → entitlement actif (exclu de la valorisation).
        $ebook = Product::create(['tenant_id' => $this->tenant->id, 'sku' => 'EBOOK', 'name' => 'Ebook', 'price_amount' => 10000, 'price_currency' => 'XOF', 'status' => 'active', 'product_type' => Product::TYPE_DIGITAL]);
        $this->sell($ebook, 1);

        // Service : non stockable, exclu de la valorisation.
        Product::create(['tenant_id' => $this->tenant->id, 'sku' => 'SRV', 'name' => 'Installation', 'price_amount' => 20000, 'price_currency' => 'XOF', 'status' => 'active', 'product_type' => Product::TYPE_SERVICE]);
    }

    #[Test]
    public function it_values_aggregate_and_serialized_and_excludes_non_stockable(): void
    {
        $this->seedMix();

        $report = $this->getJson('/api/reports/special-products', $this->auth())
            ->assertOk()
            ->assertJsonStructure([
                'aggregate_stock_value',
                'serialized' => ['in_stock', 'reserved', 'sold', 'in_stock_value'],
                'non_stockable_excluded',
                'warranties' => ['active_contracts', 'open_claims'],
                'digital' => ['active_entitlements'],
                'total_inventory_value',
            ])
            ->json();

        $this->assertSame(5000, $report['aggregate_stock_value']);              // 5 × 1000
        $this->assertSame(2, $report['serialized']['in_stock']);                // 3 reçus - 1 vendu
        $this->assertSame(1, $report['serialized']['sold']);
        $this->assertSame(1000000, $report['serialized']['in_stock_value']);    // 2 × 500000
        $this->assertSame(2, $report['non_stockable_excluded']);               // digital + service
        $this->assertSame(1, $report['warranties']['active_contracts']);
        $this->assertSame(1, $report['warranties']['open_claims']);
        $this->assertSame(1, $report['digital']['active_entitlements']);
        $this->assertSame(1005000, $report['total_inventory_value']);          // 5000 + 1 000 000
    }

    #[Test]
    public function it_returns_zeros_with_no_data(): void
    {
        $report = $this->getJson('/api/reports/special-products', $this->auth())->assertOk()->json();

        $this->assertSame(0, $report['aggregate_stock_value']);
        $this->assertSame(0, $report['serialized']['in_stock']);
        $this->assertSame(0, $report['total_inventory_value']);
        $this->assertSame(0, $report['warranties']['active_contracts']);
    }

    #[Test]
    public function the_endpoint_requires_authentication(): void
    {
        $this->getJson('/api/reports/special-products')->assertUnauthorized();
    }
}
