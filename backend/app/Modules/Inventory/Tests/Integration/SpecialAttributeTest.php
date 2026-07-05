<?php

namespace App\Modules\Inventory\Tests\Integration;

use App\Models\User;
use App\Modules\Billing\Models\Plan;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\InventoryUnit;
use App\Modules\Inventory\Models\SpecialAttributeDefinition;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Tenants\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * RC-6D — définitions dynamiques d'identifiants métier : catalogue global seedé, normalisation/
 * validation/unicité pilotées par la définition à la réception d'unités, définitions custom tenant.
 */
class SpecialAttributeTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private string $token;
    private Product $device;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        Plan::firstOrCreate(['code' => 'starter'], ['name' => 'Starter', 'price_monthly_cents' => 0, 'price_yearly_cents' => 0, 'currency' => 'XOF', 'trial_days' => 14, 'is_active' => true, 'is_public' => true, 'sort_order' => 1]);

        $this->tenant = Tenant::create(['name' => 'Attr', 'slug' => 'attr-test', 'plan' => 'starter', 'status' => 'active', 'settings' => []]);
        $user = User::create(['name' => 'M', 'email' => 'm@attr.sn', 'password' => Hash::make('x'), 'tenant_id' => $this->tenant->id]);
        $user->assignTenantRole('manager');
        $this->token = $user->createToken('api')->plainTextToken;

        Warehouse::create(['tenant_id' => $this->tenant->id, 'name' => 'WH', 'code' => 'WH-ATTR', 'is_default' => true]);
        $this->device = Product::create([
            'tenant_id' => $this->tenant->id, 'sku' => 'ROUTER', 'name' => 'Routeur 4G', 'price_amount' => 45000,
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
        return $this->postJson("/api/inventory/products/{$this->device->id}/units", ['items' => $items], $this->auth());
    }

    #[Test]
    public function the_global_catalog_is_seeded_and_listed(): void
    {
        $codes = $this->getJson('/api/inventory/special-attributes', $this->auth())
            ->assertOk()
            ->json('data.*.code');

        foreach (['imei', 'vin', 'mac_address', 'engine_number', 'plate_number', 'meter_number', 'iccid', 'medical_device_ref', 'lot_number'] as $expected) {
            $this->assertContains($expected, $codes);
        }
    }

    #[Test]
    public function a_mac_address_is_normalized_and_deduplicated_across_formats(): void
    {
        $this->receive([['serial_type' => 'mac_address', 'serial_value' => 'aa:bb:cc:dd:ee:ff']])->assertCreated();

        $unit = InventoryUnit::withoutTenantScope()->where('tenant_id', $this->tenant->id)->first();
        $this->assertSame('AABBCCDDEEFF', $unit->normalized_serial);

        // Même MAC avec un autre format de séparateurs → doublon rejeté.
        $this->receive([['serial_type' => 'mac_address', 'serial_value' => 'AA-BB-CC-DD-EE-FF']])->assertStatus(422);
    }

    #[Test]
    public function an_invalid_value_for_the_definition_regex_is_rejected(): void
    {
        // IMEI = 14-16 chiffres ; « 123 » est invalide pour la définition.
        $this->receive([['serial_type' => 'imei', 'serial_value' => '123']])
            ->assertStatus(422);

        $this->assertSame(0, InventoryUnit::withoutTenantScope()->where('tenant_id', $this->tenant->id)->count());
    }

    #[Test]
    public function a_non_unique_definition_accepts_shared_values(): void
    {
        // lot_number est seedé is_unique=false : deux unités peuvent partager le même lot.
        $this->receive([
            ['serial_type' => 'lot_number', 'serial_value' => 'LOT-2026-001'],
            ['serial_type' => 'lot_number', 'serial_value' => 'LOT-2026-001'],
        ])->assertCreated();

        $this->assertSame(2, InventoryUnit::withoutTenantScope()->where('tenant_id', $this->tenant->id)->count());
    }

    #[Test]
    public function a_tenant_can_create_and_use_a_custom_definition(): void
    {
        $this->postJson('/api/inventory/special-attributes', [
            'code' => 'pump_ref', 'label' => 'Ref pompe', 'normalization_strategy' => 'alnum_upper',
            'validation_regex' => '^PMP[0-9]{4}$',
        ], $this->auth())->assertCreated();

        // Valide selon la définition custom → accepté et normalisé.
        $this->receive([['serial_type' => 'pump_ref', 'serial_value' => 'pmp-1234']])->assertCreated();
        $this->assertSame('PMP1234', InventoryUnit::withoutTenantScope()->where('tenant_id', $this->tenant->id)->first()->normalized_serial);

        // Invalide selon la regex custom → rejeté.
        $this->receive([['serial_type' => 'pump_ref', 'serial_value' => 'xx-99']])->assertStatus(422);
    }

    #[Test]
    public function global_definitions_are_read_only_for_tenants(): void
    {
        $global = SpecialAttributeDefinition::whereNull('tenant_id')->where('code', 'imei')->firstOrFail();

        $this->patchJson("/api/inventory/special-attributes/{$global->id}", ['label' => 'Hack'], $this->auth())
            ->assertStatus(404);
    }

    #[Test]
    public function an_unknown_serial_type_still_works_with_the_default_normalizer(): void
    {
        // Compat RC-5B : sans définition, le comportement historique (SerialNormalizer) s'applique.
        $this->receive([['serial_type' => 'legacy_thing', 'serial_value' => 'ab 12']])->assertCreated();
        $this->assertSame('AB12', InventoryUnit::withoutTenantScope()->where('tenant_id', $this->tenant->id)->first()->normalized_serial);
    }
}
