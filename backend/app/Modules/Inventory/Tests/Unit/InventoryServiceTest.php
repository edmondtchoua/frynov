<?php

namespace App\Modules\Inventory\Tests\Unit;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Inventory\Exceptions\InsufficientStockException;
use App\Modules\Inventory\Models\Stock;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Services\StockService;
use App\Modules\Tenants\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InventoryServiceTest extends TestCase
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
            'name'   => 'Test Tenant',
            'slug'   => 'test-tenant',
            'plan'   => 'starter',
            'status' => 'active',
        ]);

        $this->product = Product::create([
            'tenant_id'      => $this->tenant->id,
            'sku'            => 'PRD-0001',
            'name'           => 'Boubou Test',
            'price_amount'   => 25000,
            'price_currency' => 'XOF',
            'status'         => 'active',
        ]);
    }

    #[Test]
    public function it_creates_stock_row_on_first_find_or_create(): void
    {
        $stock = $this->service->findOrCreate($this->tenant->id, $this->product->id);

        $this->assertInstanceOf(Stock::class, $stock);
        $this->assertEquals(0, $stock->quantity);
        $this->assertEquals(0, $stock->reserved_quantity);
        $this->assertDatabaseHas('stocks', ['product_id' => $this->product->id]);
    }

    #[Test]
    public function it_returns_same_stock_row_on_subsequent_calls(): void
    {
        $s1 = $this->service->findOrCreate($this->tenant->id, $this->product->id);
        $s2 = $this->service->findOrCreate($this->tenant->id, $this->product->id);

        $this->assertEquals($s1->id, $s2->id);
        $this->assertDatabaseCount('stocks', 1);
    }

    #[Test]
    public function it_increments_quantity_on_move_in(): void
    {
        $stock = $this->service->findOrCreate($this->tenant->id, $this->product->id);

        $movement = $this->service->moveIn($stock, 50, StockMovement::REASON_DELIVERY);

        $this->assertEquals(StockMovement::TYPE_IN, $movement->type);
        $this->assertEquals(50, $movement->quantity);
        $this->assertEquals(0, $movement->quantity_before);
        $this->assertEquals(50, $movement->quantity_after);
        $this->assertEquals(50, $stock->fresh()->quantity);
    }

    #[Test]
    public function it_decrements_quantity_on_move_out(): void
    {
        $stock = $this->service->findOrCreate($this->tenant->id, $this->product->id);
        $this->service->moveIn($stock, 30, StockMovement::REASON_DELIVERY);

        $stock->refresh();
        $movement = $this->service->moveOut($stock, 10, StockMovement::REASON_SALE);

        $this->assertEquals(StockMovement::TYPE_OUT, $movement->type);
        $this->assertEquals(10, $movement->quantity);
        $this->assertEquals(30, $movement->quantity_before);
        $this->assertEquals(20, $movement->quantity_after);
        $this->assertEquals(20, $stock->fresh()->quantity);
    }

    #[Test]
    public function it_throws_insufficient_stock_when_move_out_exceeds_available(): void
    {
        $stock = $this->service->findOrCreate($this->tenant->id, $this->product->id);
        $this->service->moveIn($stock, 5, StockMovement::REASON_DELIVERY);
        $stock->refresh();

        $this->expectException(InsufficientStockException::class);

        $this->service->moveOut($stock, 10, StockMovement::REASON_SALE);
    }

    #[Test]
    public function it_adjusts_to_absolute_quantity(): void
    {
        $stock = $this->service->findOrCreate($this->tenant->id, $this->product->id);
        $this->service->moveIn($stock, 100, StockMovement::REASON_DELIVERY);
        $stock->refresh();

        $movement = $this->service->adjust($stock, 73, StockMovement::REASON_COUNT, 'Counted physically');

        $this->assertEquals(StockMovement::TYPE_ADJUSTMENT, $movement->type);
        $this->assertEquals(27, $movement->quantity); // abs(73 - 100)
        $this->assertEquals(100, $movement->quantity_before);
        $this->assertEquals(73, $movement->quantity_after);
        $this->assertEquals(73, $stock->fresh()->quantity);
    }

    #[Test]
    public function it_calculates_available_as_quantity_minus_reserved(): void
    {
        $stock = $this->service->findOrCreate($this->tenant->id, $this->product->id);
        $this->service->moveIn($stock, 20, StockMovement::REASON_DELIVERY);
        $stock->refresh();

        $this->service->reserve($stock, 5);
        $stock->refresh();

        $this->assertEquals(20, $stock->quantity);
        $this->assertEquals(5, $stock->reserved_quantity);
        $this->assertEquals(15, $stock->available());
        $this->assertEquals(15, $this->service->available($stock));
    }

    #[Test]
    public function it_throws_when_reserving_more_than_available(): void
    {
        $stock = $this->service->findOrCreate($this->tenant->id, $this->product->id);
        $this->service->moveIn($stock, 3, StockMovement::REASON_DELIVERY);
        $stock->refresh();

        $this->expectException(InsufficientStockException::class);

        $this->service->reserve($stock, 5);
    }

    #[Test]
    public function it_releases_reservation(): void
    {
        $stock = $this->service->findOrCreate($this->tenant->id, $this->product->id);
        $this->service->moveIn($stock, 10, StockMovement::REASON_DELIVERY);
        $stock->refresh();
        $this->service->reserve($stock, 4);
        $stock->refresh();

        $this->service->release($stock, 4);
        $stock->refresh();

        $this->assertEquals(0, $stock->reserved_quantity);
        $this->assertEquals(10, $stock->available());
    }

    #[Test]
    public function it_detects_low_stock_items(): void
    {
        $stock = $this->service->findOrCreate($this->tenant->id, $this->product->id);
        $this->service->moveIn($stock, 3, StockMovement::REASON_DELIVERY); // below threshold of 5

        $product2 = Product::create([
            'tenant_id'      => $this->tenant->id,
            'sku'            => 'PRD-0002',
            'name'           => 'Autre produit',
            'price_amount'   => 10000,
            'price_currency' => 'XOF',
            'status'         => 'active',
        ]);
        $stock2 = $this->service->findOrCreate($this->tenant->id, $product2->id);
        $this->service->moveIn($stock2, 50, StockMovement::REASON_DELIVERY); // plenty

        $lowItems = $this->service->lowStockItems($this->tenant->id);

        $this->assertCount(1, $lowItems);
        $this->assertEquals($this->product->id, $lowItems->first()->product_id);
    }

    #[Test]
    public function it_tracks_stock_per_variant(): void
    {
        $this->product->update(['has_variants' => true]);

        $variant = ProductVariant::create([
            'tenant_id'  => $this->tenant->id,
            'product_id' => $this->product->id,
            'sku'        => 'PRD-0001-V1',
            'name'       => 'Rouge / L',
            'attributes' => ['Couleur' => 'Rouge', 'Taille' => 'L'],
        ]);

        $stockBase    = $this->service->findOrCreate($this->tenant->id, $this->product->id, null);
        $stockVariant = $this->service->findOrCreate($this->tenant->id, $this->product->id, $variant->id);

        $this->assertNotEquals($stockBase->id, $stockVariant->id);

        $this->service->moveIn($stockVariant, 15, StockMovement::REASON_DELIVERY);

        $this->assertEquals(0, $stockBase->fresh()->quantity);
        $this->assertEquals(15, $stockVariant->fresh()->quantity);
    }

    #[Test]
    public function it_resolves_stock_by_sku(): void
    {
        $stock = $this->service->findBySku('PRD-0001', $this->tenant->id);

        $this->assertInstanceOf(Stock::class, $stock);
        $this->assertEquals($this->product->id, $stock->product_id);
        $this->assertNull($stock->variant_id);
    }
}
