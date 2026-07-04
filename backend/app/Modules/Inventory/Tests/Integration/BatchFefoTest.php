<?php

namespace App\Modules\Inventory\Tests\Integration;

use App\Models\User;
use App\Modules\Billing\Models\Plan;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\ProductBatch;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Orders\Services\OrderService;
use App\Modules\Tenants\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * RC-6H — lots & péremption : réception par lot (miroir agrégé), consommation FEFO à la vente,
 * épuisement, alerte péremption, unicité du numéro de lot.
 */
class BatchFefoTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private string $token;
    private Product $milk;
    private OrderService $orders;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        Plan::firstOrCreate(['code' => 'starter'], ['name' => 'Starter', 'price_monthly_cents' => 0, 'price_yearly_cents' => 0, 'currency' => 'XOF', 'trial_days' => 14, 'is_active' => true, 'is_public' => true, 'sort_order' => 1]);

        $this->tenant = Tenant::create(['name' => 'Fefo', 'slug' => 'fefo-test', 'plan' => 'starter', 'status' => 'active', 'settings' => []]);
        $this->user = User::create(['name' => 'M', 'email' => 'm@fefo.sn', 'password' => Hash::make('x'), 'tenant_id' => $this->tenant->id]);
        $this->user->assignTenantRole('manager');
        $this->token = $this->user->createToken('api')->plainTextToken;

        Warehouse::create(['tenant_id' => $this->tenant->id, 'name' => 'WH', 'code' => 'WH-FEFO', 'is_default' => true]);
        $this->milk = Product::create([
            'tenant_id' => $this->tenant->id, 'sku' => 'LAIT', 'name' => 'Lait UHT', 'price_amount' => 1000,
            'price_currency' => 'XOF', 'status' => 'active',
            'product_type' => Product::TYPE_SIMPLE, 'stock_tracking' => Product::STOCK_TRACKING_BATCH,
        ]);
        $this->orders = $this->app->make(OrderService::class);
    }

    private function auth(): array
    {
        return ['Authorization' => "Bearer {$this->token}"];
    }

    private function receiveBatch(string $number, int $qty, ?string $expiry): \Illuminate\Testing\TestResponse
    {
        return $this->postJson("/api/inventory/products/{$this->milk->id}/batches", [
            'batch_number' => $number, 'quantity' => $qty, 'expiry_date' => $expiry,
        ], $this->auth());
    }

    private function sell(int $qty): void
    {
        $order = $this->orders->create(['items' => [['product_id' => $this->milk->id, 'quantity' => $qty]]], $this->tenant->id, $this->user->id);
        $order = $this->orders->confirm($order, $this->user->id);
        $this->orders->fulfill($order, $this->user->id);
    }

    #[Test]
    public function receiving_a_batch_creates_the_lot_and_mirrors_aggregate_stock(): void
    {
        $this->receiveBatch('LOT-A', 12, now()->addDays(20)->toDateString())->assertCreated();

        $this->assertDatabaseHas('product_batches', ['tenant_id' => $this->tenant->id, 'batch_number' => 'LOT-A', 'quantity' => 12]);
        $this->assertDatabaseHas('stocks', ['tenant_id' => $this->tenant->id, 'product_id' => $this->milk->id, 'quantity' => 12]);
    }

    #[Test]
    public function a_non_batch_product_rejects_batch_reception(): void
    {
        $simple = Product::create([
            'tenant_id' => $this->tenant->id, 'sku' => 'MUG', 'name' => 'Mug', 'price_amount' => 3000,
            'price_currency' => 'XOF', 'status' => 'active', 'product_type' => Product::TYPE_SIMPLE,
        ]);

        $this->postJson("/api/inventory/products/{$simple->id}/batches", ['batch_number' => 'X', 'quantity' => 1], $this->auth())
            ->assertStatus(422);
    }

    #[Test]
    public function a_sale_consumes_batches_fefo_and_exhausts_the_earliest(): void
    {
        $this->receiveBatch('LOT-LOIN',  5, now()->addDays(60)->toDateString())->assertCreated(); // reçu en 1er mais périme plus tard
        $this->receiveBatch('LOT-PROCHE', 5, now()->addDays(5)->toDateString())->assertCreated();

        $this->sell(7);

        // FEFO : LOT-PROCHE (périme d'abord) vidé, puis 2 pris sur LOT-LOIN.
        $proche = ProductBatch::withoutTenantScope()->where('batch_number', 'LOT-PROCHE')->first();
        $loin   = ProductBatch::withoutTenantScope()->where('batch_number', 'LOT-LOIN')->first();
        $this->assertSame(0, $proche->quantity);
        $this->assertSame(ProductBatch::STATUS_EXHAUSTED, $proche->status);
        $this->assertSame(3, $loin->quantity);
        $this->assertSame(ProductBatch::STATUS_ACTIVE, $loin->status);
    }

    #[Test]
    public function undated_batches_are_consumed_last(): void
    {
        $this->receiveBatch('LOT-SANS-DATE', 5, null)->assertCreated();
        $this->receiveBatch('LOT-DATE', 5, now()->addDays(90)->toDateString())->assertCreated();

        $this->sell(5);

        $this->assertSame(0, ProductBatch::withoutTenantScope()->where('batch_number', 'LOT-DATE')->first()->quantity);
        $this->assertSame(5, ProductBatch::withoutTenantScope()->where('batch_number', 'LOT-SANS-DATE')->first()->quantity);
    }

    #[Test]
    public function expiring_lists_only_batches_within_the_window(): void
    {
        $this->receiveBatch('LOT-URGENT', 3, now()->addDays(7)->toDateString())->assertCreated();
        $this->receiveBatch('LOT-OK', 3, now()->addDays(200)->toDateString())->assertCreated();

        $res = $this->getJson('/api/inventory/batches/expiring?days=30', $this->auth())
            ->assertOk()
            ->assertJsonPath('count', 1);

        $this->assertSame('LOT-URGENT', $res->json('data.0.batch_number'));
        $this->assertLessThanOrEqual(7, $res->json('data.0.days_left'));
    }

    #[Test]
    public function a_duplicate_batch_number_for_the_same_product_is_rejected(): void
    {
        $this->receiveBatch('LOT-A', 5, null)->assertCreated();
        $this->receiveBatch('LOT-A', 5, null)->assertStatus(422);
    }
}
