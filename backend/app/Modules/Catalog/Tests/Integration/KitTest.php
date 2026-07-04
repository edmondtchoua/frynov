<?php

namespace App\Modules\Catalog\Tests\Integration;

use App\Models\User;
use App\Modules\Billing\Models\Plan;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Exceptions\InsufficientStockException;
use App\Modules\Inventory\Services\StockService;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Services\OrderService;
use App\Modules\Tenants\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * RC-6I — kits/bundles : nomenclature + vente d'un kit virtuel = réservation/consommation du stock
 * des COMPOSANTS (confirm/fulfill/cancel).
 */
class KitTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private string $token;
    private Product $kit;
    private Product $panneau;
    private Product $batterie;
    private OrderService $orders;
    private StockService $stock;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        Plan::firstOrCreate(['code' => 'starter'], ['name' => 'Starter', 'price_monthly_cents' => 0, 'price_yearly_cents' => 0, 'currency' => 'XOF', 'trial_days' => 14, 'is_active' => true, 'is_public' => true, 'sort_order' => 1]);

        $this->tenant = Tenant::create(['name' => 'Kit', 'slug' => 'kit-test', 'plan' => 'starter', 'status' => 'active', 'settings' => []]);
        $this->user = User::create(['name' => 'M', 'email' => 'm@kit.sn', 'password' => Hash::make('x'), 'tenant_id' => $this->tenant->id]);
        $this->user->assignTenantRole('manager');
        $this->token = $this->user->createToken('api')->plainTextToken;

        $this->orders = $this->app->make(OrderService::class);
        $this->stock  = $this->app->make(StockService::class);

        $this->kit = Product::create([
            'tenant_id' => $this->tenant->id, 'sku' => 'KIT-SOLAR', 'name' => 'Kit solaire', 'price_amount' => 250000,
            'price_currency' => 'XOF', 'status' => 'active', 'product_type' => Product::TYPE_KIT,
        ]);
        $this->panneau  = $this->makeComponent('PANNEAU', 10);
        $this->batterie = $this->makeComponent('BATTERIE', 10);
    }

    private function makeComponent(string $sku, int $qty): Product
    {
        $p = Product::create([
            'tenant_id' => $this->tenant->id, 'sku' => $sku, 'name' => $sku, 'price_amount' => 50000,
            'price_currency' => 'XOF', 'status' => 'active', 'product_type' => Product::TYPE_SIMPLE,
        ]);
        $this->stock->moveIn($this->stock->findOrCreate($this->tenant->id, $p->id, null), $qty);

        return $p;
    }

    private function auth(): array
    {
        return ['Authorization' => "Bearer {$this->token}"];
    }

    private function defineBom(): void
    {
        $this->putJson("/api/catalog/products/{$this->kit->id}/components", ['components' => [
            ['product_id' => $this->panneau->id, 'quantity' => 2],
            ['product_id' => $this->batterie->id, 'quantity' => 1],
        ]], $this->auth())->assertOk()->assertJsonPath('count', 2);
    }

    private function qty(Product $p): array
    {
        $s = $this->stock->findOrCreate($this->tenant->id, $p->id, null)->fresh();

        return [$s->quantity, $s->reserved_quantity];
    }

    #[Test]
    public function only_a_kit_product_accepts_a_bom(): void
    {
        $this->putJson("/api/catalog/products/{$this->panneau->id}/components", ['components' => [
            ['product_id' => $this->batterie->id, 'quantity' => 1],
        ]], $this->auth())->assertStatus(422);
    }

    #[Test]
    public function confirming_a_kit_order_reserves_component_stock(): void
    {
        $this->defineBom();

        $order = $this->orders->create(['items' => [['product_id' => $this->kit->id, 'quantity' => 3]]], $this->tenant->id, $this->user->id);
        $this->orders->confirm($order, $this->user->id);

        // 3 kits × (2 panneaux + 1 batterie) → 6 panneaux et 3 batteries réservés ; kit sans stock propre.
        $this->assertSame([10, 6], $this->qty($this->panneau));
        $this->assertSame([10, 3], $this->qty($this->batterie));
        $this->assertSame([0, 0], $this->qty($this->kit));
    }

    #[Test]
    public function fulfilling_consumes_component_stock(): void
    {
        $this->defineBom();

        $order = $this->orders->create(['items' => [['product_id' => $this->kit->id, 'quantity' => 3]]], $this->tenant->id, $this->user->id);
        $order = $this->orders->confirm($order, $this->user->id);
        $this->orders->fulfill($order, $this->user->id);

        $this->assertSame([4, 0], $this->qty($this->panneau));   // 10 - 6
        $this->assertSame([7, 0], $this->qty($this->batterie));  // 10 - 3
        $this->assertSame(Order::STATUS_FULFILLED, $order->fresh()->status);
    }

    #[Test]
    public function cancelling_a_confirmed_kit_order_releases_components(): void
    {
        $this->defineBom();

        $order = $this->orders->create(['items' => [['product_id' => $this->kit->id, 'quantity' => 2]]], $this->tenant->id, $this->user->id);
        $order = $this->orders->confirm($order, $this->user->id);
        $this->orders->cancel($order, $this->user->id);

        $this->assertSame([10, 0], $this->qty($this->panneau));
        $this->assertSame([10, 0], $this->qty($this->batterie));
    }

    #[Test]
    public function insufficient_component_stock_blocks_the_confirm_atomically(): void
    {
        $this->defineBom();

        // 6 kits → 12 panneaux demandés, 10 disponibles.
        $order = $this->orders->create(['items' => [['product_id' => $this->kit->id, 'quantity' => 6]]], $this->tenant->id, $this->user->id);

        $this->expectException(InsufficientStockException::class);
        try {
            $this->orders->confirm($order, $this->user->id);
        } finally {
            // Atomicité : rien n'est resté réservé.
            $this->assertSame([10, 0], $this->qty($this->panneau));
            $this->assertSame([10, 0], $this->qty($this->batterie));
            $this->assertSame(Order::STATUS_DRAFT, $order->fresh()->status);
        }
    }

    #[Test]
    public function a_kit_without_bom_behaves_like_a_standard_stocked_product(): void
    {
        // Pas de nomenclature : le kit est traité comme un produit stocké classique (compat RC-5A).
        $this->stock->moveIn($this->stock->findOrCreate($this->tenant->id, $this->kit->id, null), 5);

        $order = $this->orders->create(['items' => [['product_id' => $this->kit->id, 'quantity' => 2]]], $this->tenant->id, $this->user->id);
        $order = $this->orders->confirm($order, $this->user->id);
        $this->orders->fulfill($order, $this->user->id);

        $this->assertSame([3, 0], $this->qty($this->kit));
        $this->assertSame([10, 0], $this->qty($this->panneau)); // composants intouchés
    }
}
