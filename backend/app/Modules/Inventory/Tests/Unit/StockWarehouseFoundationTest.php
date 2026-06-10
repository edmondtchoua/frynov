<?php

namespace App\Modules\Inventory\Tests\Unit;

use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\Stock;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Services\StockService;
use App\Modules\Tenants\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * RC-3A (socle stock multi-entrepôt) : findOrCreate doit respecter warehouse_id dans la clé
 * unique (l'index DB est tenant+warehouse+product+variant). Sans entrepôt, il résout l'entrepôt
 * par défaut du tenant ; sans aucun entrepôt, il retombe sur une ligne NULL (compat legacy).
 */
class StockWarehouseFoundationTest extends TestCase
{
    use RefreshDatabase;

    private StockService $service;
    private Tenant $tenant;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = $this->app->make(StockService::class);

        $this->tenant = Tenant::create([
            'name' => 'Foundation Tenant', 'slug' => 'foundation-tenant',
            'plan' => 'starter', 'status' => 'active',
        ]);

        $this->product = Product::create([
            'tenant_id' => $this->tenant->id, 'sku' => 'WHF-0001', 'name' => 'Produit WHF',
            'price_amount' => 10000, 'price_currency' => 'XOF', 'status' => 'active',
        ]);
    }

    #[Test]
    public function the_same_sku_yields_one_distinct_stock_row_per_warehouse(): void
    {
        $whA = Warehouse::create(['tenant_id' => $this->tenant->id, 'name' => 'Site A', 'code' => 'WHF-A', 'is_default' => true]);
        $whB = Warehouse::create(['tenant_id' => $this->tenant->id, 'name' => 'Site B', 'code' => 'WHF-B']);

        $stockA = $this->service->findOrCreate($this->tenant->id, $this->product->id, null, $whA->id);
        $stockB = $this->service->findOrCreate($this->tenant->id, $this->product->id, null, $whB->id);

        $this->assertNotSame($stockA->id, $stockB->id, 'chaque entrepôt a sa propre ligne de stock');
        $this->assertSame($whA->id, $stockA->warehouse_id);
        $this->assertSame($whB->id, $stockB->warehouse_id);
        $this->assertDatabaseCount('stocks', 2);

        // Idempotence : un second appel ne crée pas de doublon (la clé unique inclut l'entrepôt).
        $again = $this->service->findOrCreate($this->tenant->id, $this->product->id, null, $whB->id);
        $this->assertSame($stockB->id, $again->id);
        $this->assertDatabaseCount('stocks', 2);
    }

    #[Test]
    public function without_a_warehouse_it_resolves_the_tenant_default(): void
    {
        $def = Warehouse::create(['tenant_id' => $this->tenant->id, 'name' => 'Principal', 'code' => 'WHF-DEF', 'is_default' => true]);
        Warehouse::create(['tenant_id' => $this->tenant->id, 'name' => 'Secondaire', 'code' => 'WHF-SEC']);

        $stock = $this->service->findOrCreate($this->tenant->id, $this->product->id);

        $this->assertSame($def->id, $stock->warehouse_id, 'le stock par défaut atterrit dans l\'entrepôt is_default');
    }

    #[Test]
    public function with_no_warehouse_at_all_it_falls_back_to_a_null_row(): void
    {
        $stock = $this->service->findOrCreate($this->tenant->id, $this->product->id);

        $this->assertNull($stock->warehouse_id, 'compat legacy mono-site : ligne NULL quand le tenant n\'a aucun entrepôt');
        $this->assertDatabaseCount('stocks', 1);
    }

    #[Test]
    public function move_in_feeds_cmup_within_the_targeted_warehouse(): void
    {
        $wh = Warehouse::create(['tenant_id' => $this->tenant->id, 'name' => 'Dépôt', 'code' => 'WHF-CM', 'is_default' => true]);
        $stock = $this->service->findOrCreate($this->tenant->id, $this->product->id, null, $wh->id);

        // 10 @ 1000  → CMUP 1000 ; puis 10 @ 2000 → CMUP 1500 (perpétuel)
        $this->service->moveIn($stock, 10, StockMovement::REASON_DELIVERY, null, null, null, 1000);
        $this->service->moveIn($stock->fresh(), 10, StockMovement::REASON_DELIVERY, null, null, null, 2000);

        $fresh = Stock::findOrFail($stock->id);
        $this->assertSame(20, $fresh->quantity);
        $this->assertSame(1500, $fresh->unit_cost_cents);
        $this->assertSame(30000, $fresh->total_value_cents);
        $this->assertSame($wh->id, $fresh->warehouse_id);
    }
}
