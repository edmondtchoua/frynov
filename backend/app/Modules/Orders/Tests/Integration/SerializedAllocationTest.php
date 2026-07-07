<?php

namespace App\Modules\Orders\Tests\Integration;

use App\Models\User;
use App\Modules\Billing\Models\Plan;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Exceptions\InsufficientUnitsException;
use App\Modules\Inventory\Models\InventoryUnit;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Services\InventoryUnitService;
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
 * RC-5C — lien commande ⇄ unité sérialisée ⇄ client.
 * Réservation/vente d'unités précises (IMEI/VIN) au fil du cycle de vie d'une commande.
 */
class SerializedAllocationTest extends TestCase
{
    use RefreshDatabase;

    private const CUSTOMER_ID = '11111111-1111-4111-8111-111111111111';

    private Tenant $tenant;
    private User $user;
    private string $token;
    private Product $phone;
    private Warehouse $wh;
    private OrderService $orders;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        Plan::firstOrCreate(['code' => 'starter'], ['name' => 'Starter', 'price_monthly_cents' => 0, 'price_yearly_cents' => 0, 'currency' => 'XOF', 'trial_days' => 14, 'is_active' => true, 'is_public' => true, 'sort_order' => 1]);

        $this->tenant = Tenant::create(['name' => 'Alloc', 'slug' => 'alloc-test', 'plan' => 'starter', 'status' => 'active', 'settings' => []]);
        $this->seedCustomer(self::CUSTOMER_ID, $this->tenant->id); // RC-20 (P-5) — le customer_id doit exister
        $this->user = User::create(['name' => 'M', 'email' => 'm@alloc.sn', 'password' => Hash::make('x'), 'tenant_id' => $this->tenant->id]);
        $this->user->assignTenantRole('manager');
        $this->token = $this->user->createToken('api')->plainTextToken;

        $this->wh = Warehouse::create(['tenant_id' => $this->tenant->id, 'name' => 'WH', 'code' => 'WH-ALLOC', 'is_default' => true]);
        $this->phone = Product::create([
            'tenant_id' => $this->tenant->id, 'sku' => 'IPHONE', 'name' => 'iPhone 15', 'price_amount' => 800000,
            'price_currency' => 'XOF', 'status' => 'active',
            'product_type' => Product::TYPE_SIMPLE, 'stock_tracking' => Product::STOCK_TRACKING_SERIALIZED,
        ]);

        $this->orders = $this->app->make(OrderService::class);
    }

    private function auth(): array
    {
        return ['Authorization' => "Bearer {$this->token}"];
    }

    /** Réceptionne des IMEI via le service RC-5B (incrémente aussi le stock agrégé miroir). */
    private function receive(string ...$imeis): void
    {
        $items = array_map(fn (string $v) => ['serial_type' => 'imei', 'serial_value' => $v, 'warehouse_id' => $this->wh->id], $imeis);
        $this->app->make(InventoryUnitService::class)->registerMany($this->tenant->id, $this->phone->id, $items, $this->user->id);
    }

    private function draft(int $qty): Order
    {
        return $this->orders->create([
            'customer_id' => self::CUSTOMER_ID,
            'items'       => [['product_id' => $this->phone->id, 'quantity' => $qty]],
        ], $this->tenant->id, $this->user->id);
    }

    private function units(): \Illuminate\Support\Collection
    {
        return InventoryUnit::withoutTenantScope()->where('tenant_id', $this->tenant->id)->get();
    }

    #[Test]
    public function confirm_reserves_specific_units_and_the_aggregate_mirror(): void
    {
        $this->receive('359000000000001', '359000000000002', '359000000000003');

        $order = $this->draft(2);
        $this->orders->confirm($order, $this->user->id);

        // 2 unités précises réservées sur la ligne, 1 toujours disponible.
        $reserved = $this->units()->where('status', InventoryUnit::STATUS_RESERVED);
        $this->assertCount(2, $reserved);
        $this->assertCount(1, $this->units()->where('status', InventoryUnit::STATUS_IN_STOCK));
        foreach ($reserved as $u) {
            $this->assertSame($order->id, $u->order_id);
            $this->assertSame($order->lines->first()->id, $u->order_line_id);
        }

        // Le stock agrégé miroir réserve aussi 2.
        $this->assertDatabaseHas('stocks', [
            'tenant_id' => $this->tenant->id, 'product_id' => $this->phone->id, 'reserved_quantity' => 2,
        ]);
    }

    #[Test]
    public function fulfill_marks_units_sold_and_links_the_customer(): void
    {
        $this->receive('359000000000001', '359000000000002');

        $order = $this->draft(2);
        $order = $this->orders->confirm($order, $this->user->id);
        $this->orders->fulfill($order, $this->user->id);

        $sold = $this->units()->where('status', InventoryUnit::STATUS_SOLD);
        $this->assertCount(2, $sold);
        foreach ($sold as $u) {
            $this->assertNotNull($u->sold_at);
            $this->assertSame(self::CUSTOMER_ID, $u->customer_id);
            $this->assertSame($order->id, $u->order_id);
        }
    }

    #[Test]
    public function cancelling_a_confirmed_order_releases_the_units(): void
    {
        $this->receive('359000000000001', '359000000000002');

        $order = $this->draft(2);
        $order = $this->orders->confirm($order, $this->user->id);
        $this->assertCount(2, $this->units()->where('status', InventoryUnit::STATUS_RESERVED));

        $this->orders->cancel($order, $this->user->id);

        // Toutes redeviennent disponibles, sans rattachement résiduel.
        $available = $this->units()->where('status', InventoryUnit::STATUS_IN_STOCK);
        $this->assertCount(2, $available);
        foreach ($available as $u) {
            $this->assertNull($u->order_id);
            $this->assertNull($u->order_line_id);
            $this->assertNull($u->customer_id);
        }
    }

    #[Test]
    public function confirm_fails_and_rolls_back_when_units_are_insufficient(): void
    {
        $this->receive('359000000000001'); // 1 seule unité

        $order = $this->draft(2); // on en demande 2

        try {
            $this->orders->confirm($order, $this->user->id);
            $this->fail('InsufficientUnitsException attendue.');
        } catch (InsufficientUnitsException $e) {
            $this->assertSame(1, $e->available);
            $this->assertSame(2, $e->requested);
        }

        // Atomicité : aucune unité réservée, ni réservation agrégée résiduelle.
        $this->assertCount(0, $this->units()->where('status', InventoryUnit::STATUS_RESERVED));
        $this->assertDatabaseHas('stocks', [
            'tenant_id' => $this->tenant->id, 'product_id' => $this->phone->id, 'reserved_quantity' => 0,
        ]);
        $this->assertSame(Order::STATUS_DRAFT, $order->fresh()->status);
    }

    #[Test]
    public function two_orders_cannot_reserve_the_same_unit(): void
    {
        $this->receive('359000000000001', '359000000000002');

        $a = $this->draft(2);
        $this->orders->confirm($a, $this->user->id); // prend les 2

        $b = $this->draft(1);
        $this->expectException(InsufficientUnitsException::class);
        $this->orders->confirm($b, $this->user->id); // plus aucune disponible
    }

    #[Test]
    public function a_non_serialized_product_creates_no_units(): void
    {
        $simple = Product::create([
            'tenant_id' => $this->tenant->id, 'sku' => 'TSHIRT', 'name' => 'T-shirt', 'price_amount' => 5000,
            'price_currency' => 'XOF', 'status' => 'active', 'product_type' => Product::TYPE_SIMPLE,
        ]); // aggregate par défaut
        $stock = $this->app->make(StockService::class)->findOrCreate($this->tenant->id, $simple->id, null);
        $this->app->make(StockService::class)->moveIn($stock, 10);

        $order = $this->orders->create([
            'items' => [['product_id' => $simple->id, 'quantity' => 3]],
        ], $this->tenant->id, $this->user->id);
        $order = $this->orders->confirm($order, $this->user->id);
        $this->orders->fulfill($order, $this->user->id);

        $this->assertSame(0, InventoryUnit::withoutTenantScope()->where('product_id', $simple->id)->count());
        $this->assertSame(Order::STATUS_FULFILLED, $order->fresh()->status);
    }

    #[Test]
    public function confirm_endpoint_returns_422_when_units_are_insufficient(): void
    {
        $this->receive('359000000000001');

        $order = $this->draft(2);

        $this->postJson("/api/orders/{$order->id}/confirm", [], $this->auth())
            ->assertStatus(422)
            ->assertJsonPath('available', 1);
    }

    #[Test]
    public function units_endpoint_lists_units_attached_to_the_order(): void
    {
        $this->receive('359000000000001', '359000000000002', '359000000000003');

        $order = $this->draft(2);
        $this->orders->confirm($order, $this->user->id);

        $res = $this->getJson("/api/orders/{$order->id}/units", $this->auth())
            ->assertOk()
            ->assertJsonPath('count', 2);

        foreach ($res->json('data') as $u) {
            $this->assertSame('imei', $u['serial_type']);
            $this->assertSame(InventoryUnit::STATUS_RESERVED, $u['status']);
            $this->assertSame($order->lines->first()->id, $u['order_line_id']);
        }
    }

    #[Test]
    public function units_endpoint_does_not_leak_another_tenants_order(): void
    {
        $other = Tenant::create(['name' => 'Other', 'slug' => 'other-alloc', 'plan' => 'starter', 'status' => 'active', 'settings' => []]);
        $otherUser = User::create(['name' => 'O', 'email' => 'o@alloc.sn', 'password' => Hash::make('x'), 'tenant_id' => $other->id]);
        $otherUser->assignTenantRole('manager');
        $otherToken = $otherUser->createToken('api')->plainTextToken;

        $this->receive('359000000000001', '359000000000002');
        $order = $this->draft(2);
        $this->orders->confirm($order, $this->user->id);

        // Le tenant « other » ne doit pas atteindre la commande du premier tenant (404).
        $this->getJson("/api/orders/{$order->id}/units", ['Authorization' => "Bearer {$otherToken}"])
            ->assertStatus(404);
    }
}
