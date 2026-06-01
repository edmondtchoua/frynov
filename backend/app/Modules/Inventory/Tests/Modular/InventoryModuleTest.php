<?php

namespace App\Modules\Inventory\Tests\Modular;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Services\StockService;
use App\Modules\Tenants\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * End-to-end flow: delivery reception → sale → inventory count.
 * Validates the full scan-to-action lifecycle for an African SME.
 */
class InventoryModuleTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function full_stock_lifecycle_from_delivery_to_sale_to_count(): void
    {
        // ── Setup ──────────────────────────────────────────────────────────
        $tenant = Tenant::create(['name' => 'Boutique', 'slug' => 'boutique', 'plan' => 'starter', 'status' => 'active']);
        $user   = User::create(['name' => 'Manager', 'email' => 'mgr@boutique.sn', 'password' => Hash::make('pass'), 'tenant_id' => $tenant->id]);
        $token  = $user->createToken('api')->plainTextToken;

        $product = Product::create([
            'tenant_id' => $tenant->id, 'sku' => 'VET-0001', 'name' => 'Boubou',
            'price_amount' => 25000, 'price_currency' => 'XOF', 'status' => 'active',
        ]);

        // ── 1. Receive delivery of 50 units ────────────────────────────────
        $this->withToken($token)->postJson('/api/inventory/deliveries', [
            'reference' => 'BL-2026-001',
            'items'     => [['product_id' => $product->id, 'quantity' => 50]],
        ])->assertStatus(201);

        $this->assertEquals(50, $this->freshStock($product->id)['quantity']);

        // ── 2. Scan: sell 3 units at the register ──────────────────────────
        $this->withToken($token)->postJson('/api/inventory/scan', [
            'sku' => 'VET-0001', 'action' => 'move_out', 'quantity' => 3, 'reason' => 'sale',
        ])->assertStatus(201);

        $this->assertEquals(47, $this->freshStock($product->id)['quantity']);

        // ── 3. Scan: receive 10 more (top-up delivery) ─────────────────────
        $this->withToken($token)->postJson('/api/inventory/scan', [
            'sku' => 'VET-0001', 'action' => 'move_in', 'quantity' => 10, 'reason' => 'delivery',
        ])->assertStatus(201);

        $this->assertEquals(57, $this->freshStock($product->id)['quantity']);

        // ── 4. Physical inventory count → 55 units actually found ─────────
        $this->withToken($token)->postJson('/api/inventory/count', [
            'items' => [['product_id' => $product->id, 'quantity' => 55]],
        ])->assertOk();

        $this->assertEquals(55, $this->freshStock($product->id)['quantity']);

        // ── 5. Check audit trail: 4 movements recorded ────────────────────
        $this->assertDatabaseCount('stock_movements', 4);
        // orderBy created_at ensures deterministic ordering regardless of index scan order
        $types = StockMovement::withoutTenantScope()
            ->where('product_id', $product->id)
            ->orderBy('created_at')
            ->pluck('type')
            ->toArray();
        $this->assertEquals(['in', 'out', 'in', 'adjustment'], $types);

        // ── 6. Attempt to over-sell ────────────────────────────────────────
        $this->withToken($token)->postJson('/api/inventory/scan', [
            'sku' => 'VET-0001', 'action' => 'move_out', 'quantity' => 999,
        ])->assertStatus(422);

        // Stock unchanged after failed attempt
        $this->assertEquals(55, $this->freshStock($product->id)['quantity']);
    }

    #[Test]
    public function variant_stock_is_tracked_independently(): void
    {
        $tenant  = Tenant::create(['name' => 'B', 'slug' => 'b', 'plan' => 'starter', 'status' => 'active']);
        $user    = User::create(['name' => 'U', 'email' => 'u@b.sn', 'password' => Hash::make('p'), 'tenant_id' => $tenant->id]);
        $token   = $user->createToken('api')->plainTextToken;

        $product = Product::create([
            'tenant_id' => $tenant->id, 'sku' => 'VET-0002', 'name' => 'Boubou V',
            'price_amount' => 20000, 'price_currency' => 'XOF', 'status' => 'active', 'has_variants' => true,
        ]);

        $varL = ProductVariant::create(['tenant_id' => $tenant->id, 'product_id' => $product->id, 'sku' => 'VET-0002-V1', 'name' => 'L', 'attributes' => ['Taille' => 'L']]);
        $varXL = ProductVariant::create(['tenant_id' => $tenant->id, 'product_id' => $product->id, 'sku' => 'VET-0002-V2', 'name' => 'XL', 'attributes' => ['Taille' => 'XL']]);

        $stockService = $this->app->make(StockService::class);

        // Receive L × 20, XL × 8
        $this->withToken($token)->postJson('/api/inventory/deliveries', [
            'items' => [
                ['product_id' => $product->id, 'variant_id' => $varL->id,  'quantity' => 20],
                ['product_id' => $product->id, 'variant_id' => $varXL->id, 'quantity' => 8],
            ],
        ])->assertStatus(201);

        // Sell VET-0002-V1 (L) × 5 via scan
        $this->withToken($token)->postJson('/api/inventory/scan', [
            'sku' => 'VET-0002-V1', 'action' => 'move_out', 'quantity' => 5,
        ])->assertStatus(201);

        $stockL  = $stockService->findOrCreate($tenant->id, $product->id, $varL->id)->fresh();
        $stockXL = $stockService->findOrCreate($tenant->id, $product->id, $varXL->id)->fresh();

        $this->assertEquals(15, $stockL->quantity);
        $this->assertEquals(8,  $stockXL->quantity);
    }

    private function freshStock(string $productId): array
    {
        return \App\Modules\Inventory\Models\Stock::where('product_id', $productId)
            ->whereNull('variant_id')
            ->firstOrFail()
            ->toArray();
    }
}
