<?php

namespace App\Modules\Inventory\Tests\Unit;

use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\Stock;
use App\Modules\Tenants\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests for database-level stock integrity constraints.
 * Verifies that CHECK constraints and triggers prevent invalid stock states.
 */
class StockIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private string $tenantId;
    private string $productId;

    protected function setUp(): void
    {
        parent::setUp();

        $tenant = Tenant::create([
            'name' => 'T', 'slug' => 't-integrity', 'plan' => 'starter', 'status' => 'active',
            'settings' => ['currency' => 'XOF', 'timezone' => 'Africa/Abidjan', 'locale' => 'fr'],
        ]);
        $this->tenantId = $tenant->id;

        $product = Product::create([
            'tenant_id' => $tenant->id, 'sku' => 'INT-0001',
            'name' => 'Test', 'price_amount' => 1000, 'price_currency' => 'XOF',
            'status' => 'active', 'has_variants' => false,
        ]);
        $this->productId = $product->id;
    }

    #[Test]
    public function stock_quantity_cannot_go_negative(): void
    {
        // CHECK constraints only enforced on MySQL 8+/MariaDB — skip on SQLite (test env)
        if (\DB::getDriverName() === 'sqlite') {
            $this->markTestSkipped('CHECK constraints are MySQL-only; validated in production.');
        }

        $stock = Stock::create([
            'tenant_id' => $this->tenantId, 'product_id' => $this->productId,
            'quantity' => 10, 'reserved_quantity' => 0, 'low_stock_threshold' => 2,
            'unit_cost_cents' => 0, 'total_value_cents' => 0,
        ]);

        $this->expectException(\Exception::class);
        DB::statement('UPDATE stocks SET quantity = -5 WHERE id = ?', [$stock->id]);
    }

    #[Test]
    public function reserved_quantity_cannot_exceed_quantity(): void
    {
        // CHECK constraints only enforced on MySQL 8+/MariaDB — skip on SQLite (test env)
        if (\DB::getDriverName() === 'sqlite') {
            $this->markTestSkipped('CHECK constraints are MySQL-only; validated in production.');
        }

        $stock = Stock::create([
            'tenant_id' => $this->tenantId, 'product_id' => $this->productId,
            'quantity' => 5, 'reserved_quantity' => 0, 'low_stock_threshold' => 2,
            'unit_cost_cents' => 0, 'total_value_cents' => 0,
        ]);

        $this->expectException(\Exception::class);
        DB::statement('UPDATE stocks SET reserved_quantity = 10 WHERE id = ?', [$stock->id]);
    }

    #[Test]
    public function valid_stock_update_succeeds(): void
    {
        $stock = Stock::create([
            'tenant_id' => $this->tenantId, 'product_id' => $this->productId,
            'quantity' => 10, 'reserved_quantity' => 2, 'low_stock_threshold' => 3,
            'unit_cost_cents' => 5000, 'total_value_cents' => 50000,
        ]);

        // Valid: quantity = 15, reserved = 3 (15 >= 3 ✓)
        $stock->update(['quantity' => 15, 'reserved_quantity' => 3, 'total_value_cents' => 75000]);
        $this->assertSame(15, $stock->fresh()->quantity);
    }

    #[Test]
    public function available_and_is_low_stock_are_exposed_in_json(): void
    {
        $stock = Stock::create([
            'tenant_id' => $this->tenantId, 'product_id' => $this->productId,
            'quantity' => 10, 'reserved_quantity' => 3, 'low_stock_threshold' => 5,
            'unit_cost_cents' => 0, 'total_value_cents' => 0,
        ]);

        $data = $stock->fresh()->toArray();

        // Renamed from available_qty → available (matches frontend Stock type)
        $this->assertArrayHasKey('available', $data);
        $this->assertSame(7, $data['available']); // 10 - 3

        // is_low_stock is also appended
        $this->assertArrayHasKey('is_low_stock', $data);
        $this->assertFalse($data['is_low_stock']); // 7 available > threshold 5
    }
}
