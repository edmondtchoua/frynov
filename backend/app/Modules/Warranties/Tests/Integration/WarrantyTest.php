<?php

namespace App\Modules\Warranties\Tests\Integration;

use App\Models\User;
use App\Modules\Billing\Models\Plan;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\InventoryUnit;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Services\InventoryUnitService;
use App\Modules\Inventory\Services\StockService;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Services\OrderService;
use App\Modules\Tenants\Models\Tenant;
use App\Modules\Warranties\Models\WarrantyContract;
use App\Modules\Warranties\Models\WarrantyPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * RC-5D — garanties : politique produit + contrat généré automatiquement à la vente, rattaché au
 * client et (pour le sérialisé) à l'unité vendue.
 */
class WarrantyTest extends TestCase
{
    use RefreshDatabase;

    private const CUSTOMER_ID = '22222222-2222-4222-8222-222222222222';

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

        $this->tenant = Tenant::create(['name' => 'War', 'slug' => 'war-test', 'plan' => 'starter', 'status' => 'active', 'settings' => []]);
        $this->user = User::create(['name' => 'M', 'email' => 'm@war.sn', 'password' => Hash::make('x'), 'tenant_id' => $this->tenant->id]);
        $this->user->assignTenantRole('manager');
        $this->token = $this->user->createToken('api')->plainTextToken;

        $this->wh = Warehouse::create(['tenant_id' => $this->tenant->id, 'name' => 'WH', 'code' => 'WH-WAR', 'is_default' => true]);
        $this->orders = $this->app->make(OrderService::class);
    }

    private function auth(): array
    {
        return ['Authorization' => "Bearer {$this->token}"];
    }

    private function policy(int $months = 12): WarrantyPolicy
    {
        return WarrantyPolicy::create([
            'tenant_id' => $this->tenant->id, 'name' => "Garantie {$months} mois",
            'duration_months' => $months, 'is_active' => true,
        ]);
    }

    private function serializedPhone(?string $policyId): Product
    {
        return Product::create([
            'tenant_id' => $this->tenant->id, 'sku' => 'IPHONE', 'name' => 'iPhone 15', 'price_amount' => 800000,
            'price_currency' => 'XOF', 'status' => 'active',
            'product_type' => Product::TYPE_SIMPLE, 'stock_tracking' => Product::STOCK_TRACKING_SERIALIZED,
            'warranty_policy_id' => $policyId,
        ]);
    }

    private function receive(Product $p, string ...$imeis): void
    {
        $items = array_map(fn (string $v) => ['serial_type' => 'imei', 'serial_value' => $v, 'warehouse_id' => $this->wh->id], $imeis);
        $this->app->make(InventoryUnitService::class)->registerMany($this->tenant->id, $p->id, $items, $this->user->id);
    }

    private function sell(Product $p, int $qty): Order
    {
        $order = $this->orders->create([
            'customer_id' => self::CUSTOMER_ID,
            'items'       => [['product_id' => $p->id, 'quantity' => $qty]],
        ], $this->tenant->id, $this->user->id);
        $order = $this->orders->confirm($order, $this->user->id);

        return $this->orders->fulfill($order, $this->user->id);
    }

    #[Test]
    public function fulfilling_a_serialized_order_issues_one_contract_per_unit_linked_to_the_customer(): void
    {
        $policy = $this->policy(12);
        $phone  = $this->serializedPhone($policy->id);
        $this->receive($phone, '359000000000001', '359000000000002');

        $order = $this->sell($phone, 2);

        $contracts = WarrantyContract::withoutTenantScope()->where('order_id', $order->id)->get();
        $this->assertCount(2, $contracts);

        foreach ($contracts as $c) {
            $this->assertSame(self::CUSTOMER_ID, $c->customer_id);
            $this->assertNotNull($c->inventory_unit_id);
            $this->assertNotNull($c->serial_value);
            $this->assertSame(WarrantyContract::STATUS_ACTIVE, $c->status);
            // ends_at = starts_at + 12 mois
            $this->assertEquals(12, $c->starts_at->diffInMonths($c->ends_at));
        }

        // L'unité vendue porte la période de garantie.
        $unit = InventoryUnit::withoutTenantScope()->where('order_id', $order->id)->first();
        $this->assertNotNull($unit->warranty_started_at);
        $this->assertNotNull($unit->warranty_ends_at);
    }

    #[Test]
    public function a_non_serialized_product_with_a_policy_issues_one_contract_per_unit_sold(): void
    {
        $policy = $this->policy(24);
        $simple = Product::create([
            'tenant_id' => $this->tenant->id, 'sku' => 'BLENDER', 'name' => 'Blender', 'price_amount' => 30000,
            'price_currency' => 'XOF', 'status' => 'active', 'product_type' => Product::TYPE_SIMPLE,
            'warranty_policy_id' => $policy->id,
        ]);
        $stock = $this->app->make(StockService::class)->findOrCreate($this->tenant->id, $simple->id, null);
        $this->app->make(StockService::class)->moveIn($stock, 10);

        $order = $this->sell($simple, 3);

        $contracts = WarrantyContract::withoutTenantScope()->where('order_id', $order->id)->get();
        $this->assertCount(3, $contracts); // RC-6F : un contrat PAR EXEMPLAIRE (qty 3 → 3 contrats)
        $this->assertNull($contracts->first()->inventory_unit_id);
        $this->assertEquals(24, $contracts->first()->starts_at->diffInMonths($contracts->first()->ends_at));
    }

    #[Test]
    public function a_product_without_policy_issues_no_contract(): void
    {
        $phone = $this->serializedPhone(null);
        $this->receive($phone, '359000000000001');

        $order = $this->sell($phone, 1);

        $this->assertSame(0, WarrantyContract::withoutTenantScope()->where('order_id', $order->id)->count());
    }

    #[Test]
    public function issuing_is_idempotent_per_order_line(): void
    {
        $policy = $this->policy(12);
        $phone  = $this->serializedPhone($policy->id);
        $this->receive($phone, '359000000000001');
        $order = $this->sell($phone, 1);

        // Réémettre manuellement ne crée pas de doublon (garde idempotence).
        $this->app->make(\App\Modules\Warranties\Services\WarrantyService::class)
            ->issueForOrder($order->fresh('lines'), $this->user->id);

        $this->assertSame(1, WarrantyContract::withoutTenantScope()->where('order_id', $order->id)->count());
    }

    #[Test]
    public function api_creates_a_policy_and_attaches_it_to_a_product(): void
    {
        $this->postJson('/api/warranties/policies', ['name' => 'Garantie 6 mois', 'duration_months' => 6], $this->auth())
            ->assertCreated()
            ->assertJsonPath('data.duration_months', 6);

        $policyId = WarrantyPolicy::where('tenant_id', $this->tenant->id)->first()->id;
        $phone = $this->serializedPhone(null);

        $this->postJson("/api/warranties/products/{$phone->id}/policy", ['warranty_policy_id' => $policyId], $this->auth())
            ->assertOk()
            ->assertJsonPath('data.warranty_policy_id', $policyId);

        $this->assertSame($policyId, $phone->fresh()->warranty_policy_id);
    }

    #[Test]
    public function api_lists_warranty_contracts_for_an_order(): void
    {
        $policy = $this->policy(12);
        $phone  = $this->serializedPhone($policy->id);
        $this->receive($phone, '359000000000001', '359000000000002');
        $order = $this->sell($phone, 2);

        $this->getJson("/api/warranties/orders/{$order->id}", $this->auth())
            ->assertOk()
            ->assertJsonPath('count', 2)
            ->assertJsonPath('data.0.policy_name', 'Garantie 12 mois');
    }

    #[Test]
    public function order_warranties_endpoint_isolates_tenants(): void
    {
        $policy = $this->policy(12);
        $phone  = $this->serializedPhone($policy->id);
        $this->receive($phone, '359000000000001');
        $order = $this->sell($phone, 1);

        $other = Tenant::create(['name' => 'Other', 'slug' => 'other-war', 'plan' => 'starter', 'status' => 'active', 'settings' => []]);
        $otherUser = User::create(['name' => 'O', 'email' => 'o@war.sn', 'password' => Hash::make('x'), 'tenant_id' => $other->id]);
        $otherUser->assignTenantRole('manager');
        $otherToken = $otherUser->createToken('api')->plainTextToken;

        $this->getJson("/api/warranties/orders/{$order->id}", ['Authorization' => "Bearer {$otherToken}"])
            ->assertStatus(404);
    }
}
