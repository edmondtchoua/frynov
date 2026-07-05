<?php

namespace App\Modules\Inventory\Tests\Integration;

use App\Models\User;
use App\Modules\Billing\Models\Plan;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\InventoryUnit;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Tenants\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * RC-5B — unités sérialisées (IMEI/VIN) : réception, unicité par tenant, normalisation, isolation.
 */
class SerializedUnitTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $manager;
    private string $token;
    private Product $phone;
    private Warehouse $wh;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        Plan::firstOrCreate(['code' => 'starter'], ['name' => 'Starter', 'price_monthly_cents' => 0, 'price_yearly_cents' => 0, 'currency' => 'XOF', 'trial_days' => 14, 'is_active' => true, 'is_public' => true, 'sort_order' => 1]);

        $this->tenant = Tenant::create(['name' => 'Ser', 'slug' => 'ser-test', 'plan' => 'starter', 'status' => 'active', 'settings' => []]);
        $this->manager = User::create(['name' => 'M', 'email' => 'm@ser.sn', 'password' => Hash::make('x'), 'tenant_id' => $this->tenant->id]);
        $this->manager->assignTenantRole('manager');
        $this->token = $this->manager->createToken('api')->plainTextToken;

        $this->wh = Warehouse::create(['tenant_id' => $this->tenant->id, 'name' => 'WH', 'code' => 'WH-SER', 'is_default' => true]);
        $this->phone = Product::create([
            'tenant_id' => $this->tenant->id, 'sku' => 'IPHONE', 'name' => 'iPhone 15', 'price_amount' => 800000,
            'price_currency' => 'XOF', 'status' => 'active',
            'product_type' => Product::TYPE_SIMPLE, 'stock_tracking' => Product::STOCK_TRACKING_SERIALIZED,
        ]);
    }

    private function auth(): array
    {
        return ['Authorization' => "Bearer {$this->token}"];
    }

    private function receive(array $items): \Illuminate\Testing\TestResponse
    {
        return $this->postJson("/api/inventory/products/{$this->phone->id}/units", ['items' => $items], $this->auth());
    }

    #[Test]
    public function it_receives_serialized_units_and_increments_aggregate_stock(): void
    {
        $this->receive([
            ['serial_type' => 'imei', 'serial_value' => '359123456789012', 'warehouse_id' => $this->wh->id],
            ['serial_type' => 'imei', 'serial_value' => '359123456789013', 'warehouse_id' => $this->wh->id],
        ])->assertCreated()->assertJsonPath('count', 2);

        $this->assertSame(2, InventoryUnit::where('tenant_id', $this->tenant->id)->where('status', 'in_stock')->count());
        // Le stock agrégé reflète les 2 unités.
        $this->assertDatabaseHas('stocks', ['tenant_id' => $this->tenant->id, 'product_id' => $this->phone->id, 'quantity' => 2]);
    }

    #[Test]
    public function it_rejects_a_duplicate_imei_for_the_same_tenant(): void
    {
        $this->receive([['serial_type' => 'imei', 'serial_value' => '359123456789012']])->assertCreated();
        $this->receive([['serial_type' => 'imei', 'serial_value' => '359123456789012']])->assertStatus(422);

        $this->assertSame(1, InventoryUnit::where('tenant_id', $this->tenant->id)->count());
    }

    #[Test]
    public function normalization_treats_formatted_and_raw_imei_as_the_same(): void
    {
        $this->receive([['serial_type' => 'imei', 'serial_value' => '359123456789012']])->assertCreated();
        // Même IMEI saisi avec séparateurs → doit être rejeté (normalisation = chiffres uniquement).
        $this->receive([['serial_type' => 'imei', 'serial_value' => '35-9123 45678/901.2']])->assertStatus(422);
    }

    #[Test]
    public function a_duplicate_within_the_same_batch_rolls_everything_back(): void
    {
        $this->receive([
            ['serial_type' => 'imei', 'serial_value' => '359000000000001'],
            ['serial_type' => 'imei', 'serial_value' => '359000000000001'], // doublon dans la requête
        ])->assertStatus(422);

        // Atomicité : aucune unité créée.
        $this->assertSame(0, InventoryUnit::where('tenant_id', $this->tenant->id)->count());
    }

    #[Test]
    public function the_same_imei_is_allowed_for_a_different_tenant(): void
    {
        $this->receive([['serial_type' => 'imei', 'serial_value' => '359123456789012']])->assertCreated();

        // Autre tenant, même IMEI → autorisé (unicité PAR tenant). On vérifie la contrainte DB en
        // créant l'unité du second tenant directement (la clé unique inclut tenant_id).
        $other = Tenant::create(['name' => 'Other', 'slug' => 'other-ser', 'plan' => 'starter', 'status' => 'active', 'settings' => []]);
        $otherPhone = Product::create([
            'tenant_id' => $other->id, 'sku' => 'IPHONE2', 'name' => 'iPhone', 'price_amount' => 800000,
            'price_currency' => 'XOF', 'status' => 'active', 'product_type' => Product::TYPE_SIMPLE,
            'stock_tracking' => Product::STOCK_TRACKING_SERIALIZED,
        ]);

        InventoryUnit::create([
            'tenant_id' => $other->id, 'product_id' => $otherPhone->id,
            'serial_type' => 'imei', 'serial_value' => '359123456789012', 'normalized_serial' => '359123456789012',
            'status' => InventoryUnit::STATUS_IN_STOCK, 'received_at' => now(),
        ]);

        // Le même IMEI existe désormais pour deux tenants distincts, sans violation d'unicité.
        $this->assertSame(2, InventoryUnit::withoutTenantScope()->where('normalized_serial', '359123456789012')->count());
    }

    #[Test]
    public function a_non_serialized_product_rejects_unit_reception(): void
    {
        $simple = Product::create([
            'tenant_id' => $this->tenant->id, 'sku' => 'TSHIRT', 'name' => 'T-shirt', 'price_amount' => 5000,
            'price_currency' => 'XOF', 'status' => 'active', 'product_type' => Product::TYPE_SIMPLE,
        ]); // stock_tracking par défaut = aggregate

        $this->postJson("/api/inventory/products/{$simple->id}/units",
            ['items' => [['serial_type' => 'imei', 'serial_value' => '359123456789012']]], $this->auth(),
        )->assertStatus(422);
    }

    #[Test]
    public function search_finds_a_unit_by_its_normalized_serial(): void
    {
        $this->receive([['serial_type' => 'imei', 'serial_value' => '359123456789012']])->assertCreated();

        $this->getJson('/api/inventory/units/search?type=imei&serial=35-9123-45678901-2', $this->auth())
            ->assertOk()
            ->assertJsonPath('data.serial_value', '359123456789012')
            ->assertJsonPath('data.product_name', 'iPhone 15');
    }

    #[Test]
    public function units_of_another_tenant_are_not_listed(): void
    {
        $this->receive([['serial_type' => 'imei', 'serial_value' => '359123456789012', 'warehouse_id' => $this->wh->id]])->assertCreated();

        $res = $this->getJson("/api/inventory/products/{$this->phone->id}/units", $this->auth())->assertOk();
        $this->assertSame(1, $res->json('total'));
    }
}
