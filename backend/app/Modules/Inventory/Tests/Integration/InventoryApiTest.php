<?php

namespace App\Modules\Inventory\Tests\Integration;

use App\Models\User;
use App\Modules\Billing\Models\Plan;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\Stock;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Services\StockService;
use App\Modules\Tenants\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InventoryApiTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private string $token;
    private Product $product;
    private StockService $stockService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stockService = $this->app->make(StockService::class);

        // Sprint 11: mutation routes require manager|admin role
        Role::firstOrCreate(['name' => 'admin',   'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        Plan::firstOrCreate(['code' => 'starter'], ['name' => 'Starter', 'price_monthly_cents' => 0, 'price_yearly_cents' => 0, 'currency' => 'XOF', 'trial_days' => 14, 'is_active' => true, 'is_public' => true, 'sort_order' => 1]);

        $this->tenant = Tenant::create([
            'name'     => 'Boutique Test',
            'slug'     => 'boutique-test',
            'plan'     => 'starter',
            'status'   => 'active',
            'settings' => [],
        ]);

        $this->user = User::create([
            'name'      => 'Manager',
            'email'     => 'manager@boutique-test.sn',
            'password'  => Hash::make('Secret123!'),
            'tenant_id' => $this->tenant->id,
        ]);
        $this->user->assignTenantRole('manager'); // needed for move-in/move-out/adjust (Sprint 11 role guard)

        $this->token = $this->user->createToken('api')->plainTextToken;

        $this->product = Product::create([
            'tenant_id'      => $this->tenant->id,
            'sku'            => 'VET-0001',
            'name'           => 'Boubou Sénégalais',
            'price_amount'   => 25000,
            'price_currency' => 'XOF',
            'status'         => 'active',
        ]);
    }

    #[Test]
    public function it_returns_stock_for_product(): void
    {
        $stock = $this->stockService->findOrCreate($this->tenant->id, $this->product->id);
        $this->stockService->moveIn($stock, 42, StockMovement::REASON_DELIVERY);

        $response = $this->withToken($this->token)
            ->getJson("/api/inventory/stock/{$this->product->id}");

        $response->assertOk()
            ->assertJsonPath('stock.quantity', 42)
            ->assertJsonPath('available', 42)
            ->assertJsonPath('is_low_stock', false);
    }

    #[Test]
    public function it_moves_stock_in_via_api(): void
    {
        $response = $this->withToken($this->token)->postJson(
            "/api/inventory/stock/{$this->product->id}/move-in",
            ['quantity' => 30, 'reason' => 'delivery', 'reference' => 'BL-2026-001'],
        );

        $response->assertStatus(201)
            ->assertJsonPath('movement.type', 'in')
            ->assertJsonPath('movement.quantity', 30)
            ->assertJsonPath('stock.quantity', 30);

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $this->product->id,
            'type'       => 'in',
            'quantity'   => 30,
            'reason'     => 'delivery',
            'reference'  => 'BL-2026-001',
        ]);
    }

    #[Test]
    public function it_moves_stock_out_via_api(): void
    {
        $stock = $this->stockService->findOrCreate($this->tenant->id, $this->product->id);
        $this->stockService->moveIn($stock, 20, StockMovement::REASON_DELIVERY);

        $response = $this->withToken($this->token)->postJson(
            "/api/inventory/stock/{$this->product->id}/move-out",
            ['quantity' => 5, 'reason' => 'sale'],
        );

        $response->assertStatus(201)
            ->assertJsonPath('movement.type', 'out')
            ->assertJsonPath('stock.quantity', 15);
    }

    #[Test]
    public function it_rejects_move_out_when_insufficient_stock(): void
    {
        $stock = $this->stockService->findOrCreate($this->tenant->id, $this->product->id);
        $this->stockService->moveIn($stock, 3, StockMovement::REASON_DELIVERY);

        $response = $this->withToken($this->token)->postJson(
            "/api/inventory/stock/{$this->product->id}/move-out",
            ['quantity' => 10, 'reason' => 'sale'],
        );

        $response->assertStatus(422)
            ->assertJsonPath('available', 3);
    }

    #[Test]
    public function it_adjusts_stock_to_counted_quantity(): void
    {
        $stock = $this->stockService->findOrCreate($this->tenant->id, $this->product->id);
        $this->stockService->moveIn($stock, 100, StockMovement::REASON_DELIVERY);

        $response = $this->withToken($this->token)->postJson(
            "/api/inventory/stock/{$this->product->id}/adjust",
            ['quantity' => 87, 'note' => 'Inventaire du 30/05/2026'],
        );

        $response->assertOk()
            ->assertJsonPath('movement.type', 'adjustment')
            ->assertJsonPath('stock.quantity', 87);
    }

    #[Test]
    public function it_scans_sku_and_performs_move_in(): void
    {
        $response = $this->withToken($this->token)->postJson('/api/inventory/scan', [
            'sku'      => 'VET-0001',
            'action'   => 'move_in',
            'quantity' => 10,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('movement.type', 'in')
            ->assertJsonPath('movement.quantity', 10);
    }

    #[Test]
    public function it_scans_sku_and_checks_stock_without_movement(): void
    {
        $stock = $this->stockService->findOrCreate($this->tenant->id, $this->product->id);
        $this->stockService->moveIn($stock, 17, StockMovement::REASON_DELIVERY);

        $response = $this->withToken($this->token)->postJson('/api/inventory/scan', [
            'sku'    => 'VET-0001',
            'action' => 'check',
        ]);

        $response->assertOk()
            ->assertJsonPath('sku', 'VET-0001')
            ->assertJsonPath('available', 17);

        $this->assertDatabaseCount('stock_movements', 1); // only the setup moveIn, no new one
    }

    #[Test]
    public function it_returns_404_for_unknown_sku_on_scan(): void
    {
        $response = $this->withToken($this->token)->postJson('/api/inventory/scan', [
            'sku'      => 'UNKNOWN-9999',
            'action'   => 'move_in',
            'quantity' => 1,
        ]);

        $response->assertStatus(404);
    }

    #[Test]
    public function it_processes_batch_delivery(): void
    {
        $product2 = Product::create([
            'tenant_id'      => $this->tenant->id,
            'sku'            => 'CHN-0001',
            'name'           => 'Tissu Bazin',
            'price_amount'   => 8000,
            'price_currency' => 'XOF',
            'status'         => 'active',
        ]);

        $response = $this->withToken($this->token)->postJson('/api/inventory/deliveries', [
            'reference' => 'BL-2026-042',
            'items'     => [
                ['product_id' => $this->product->id, 'quantity' => 30],
                ['product_id' => $product2->id,       'quantity' => 15],
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('count', 2);

        $this->assertDatabaseCount('stock_movements', 2);
        $this->assertEquals(30, Stock::where('product_id', $this->product->id)->first()->quantity);
        $this->assertEquals(15, Stock::where('product_id', $product2->id)->first()->quantity);
    }

    #[Test]
    public function it_returns_low_stock_alerts(): void
    {
        // Product with 2 units (below default threshold of 5)
        $stock = $this->stockService->findOrCreate($this->tenant->id, $this->product->id);
        $this->stockService->moveIn($stock, 2, StockMovement::REASON_DELIVERY);

        // Product with 50 units (fine)
        $product2 = Product::create([
            'tenant_id' => $this->tenant->id, 'sku' => 'PRD-0002', 'name' => 'Autre',
            'price_amount' => 5000, 'price_currency' => 'XOF', 'status' => 'active',
        ]);
        $stock2 = $this->stockService->findOrCreate($this->tenant->id, $product2->id);
        $this->stockService->moveIn($stock2, 50, StockMovement::REASON_DELIVERY);

        $response = $this->withToken($this->token)->getJson('/api/inventory/alerts');

        $response->assertOk();
        $this->assertCount(1, $response->json());
        $this->assertEquals($this->product->id, $response->json()[0]['product_id']);
    }

    #[Test]
    public function it_returns_movement_history_paginated(): void
    {
        $stock = $this->stockService->findOrCreate($this->tenant->id, $this->product->id);
        $this->stockService->moveIn($stock, 100, StockMovement::REASON_DELIVERY);
        $stock->refresh();
        $this->stockService->moveOut($stock, 10, StockMovement::REASON_SALE);

        $response = $this->withToken($this->token)
            ->getJson("/api/inventory/stock/{$this->product->id}/movements");

        $response->assertOk();
        $this->assertEquals(2, $response->json('total'));
    }

    #[Test]
    public function it_rejects_requests_without_authentication(): void
    {
        $this->getJson('/api/inventory/stock')->assertUnauthorized();
        $this->postJson('/api/inventory/scan', [])->assertUnauthorized();
        $this->postJson('/api/inventory/deliveries', [])->assertUnauthorized();
    }
}
