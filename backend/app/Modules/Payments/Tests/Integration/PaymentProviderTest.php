<?php

namespace App\Modules\Payments\Tests\Integration;

use App\Models\User;
use App\Modules\Billing\Models\Plan;
use App\Modules\Payments\Support\PaymentMethodCatalog;
use App\Modules\Tenants\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * P6 — checkout commercial : un moyen SPÉCIFIQUE (provider du marché) est accepté à
 * l'enregistrement d'un paiement, dérive la CATÉGORIE canonique `Payment.method` et est
 * tracé en référence. L'enum `Payment.method` (et la compat ascendante) restent intacts.
 */
class PaymentProviderTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['admin', 'manager', 'member'] as $r) {
            Role::firstOrCreate(['name' => $r, 'guard_name' => 'web']);
        }
        Plan::firstOrCreate(['code' => 'starter'], ['name' => 'Starter', 'price_monthly_cents' => 0, 'price_yearly_cents' => 0, 'currency' => 'XOF', 'trial_days' => 14, 'is_active' => true, 'is_public' => true, 'sort_order' => 1]);

        $tenant = Tenant::create(['name' => 'T', 'slug' => 'pay-prov', 'plan' => 'starter', 'status' => 'active', 'settings' => []]);
        $user   = User::create(['name' => 'M', 'email' => 'm@pay-prov.sn', 'password' => bcrypt('x'), 'tenant_id' => $tenant->id]);
        $user->assignTenantRole('manager');
        $this->token = $user->createToken('api')->plainTextToken;
    }

    #[Test]
    public function a_specific_provider_maps_to_the_canonical_method_and_is_traced(): void
    {
        $res = $this->withToken($this->token)->postJson('/api/payments', [
            'amount_cents' => 5000, 'currency' => 'XOF', 'provider' => 'wave',
        ])->assertCreated();

        $this->assertSame('mobile_money', $res->json('data.method')); // catégorie canonique
        $this->assertSame('wave', $res->json('data.reference'));        // provider tracé
    }

    #[Test]
    public function an_explicit_reference_is_kept_when_a_provider_is_given(): void
    {
        $res = $this->withToken($this->token)->postJson('/api/payments', [
            'amount_cents' => 5000, 'currency' => 'XOF', 'provider' => 'orange_money', 'reference' => 'TX-123',
        ])->assertCreated();

        $this->assertSame('mobile_money', $res->json('data.method'));
        $this->assertSame('TX-123', $res->json('data.reference'));
    }

    #[Test]
    public function the_legacy_method_field_still_works(): void
    {
        $this->withToken($this->token)->postJson('/api/payments', [
            'amount_cents' => 5000, 'currency' => 'XOF', 'method' => 'cash',
        ])->assertCreated()->assertJsonPath('data.method', 'cash');
    }

    #[Test]
    public function neither_method_nor_provider_is_rejected(): void
    {
        $this->withToken($this->token)->postJson('/api/payments', [
            'amount_cents' => 5000, 'currency' => 'XOF',
        ])->assertStatus(422);
    }

    #[Test]
    public function the_catalog_maps_providers_to_canonical_categories(): void
    {
        $this->assertSame('mobile_money', PaymentMethodCatalog::categoryFor('mpesa'));
        $this->assertSame('transfer', PaymentMethodCatalog::categoryFor('bank_transfer'));
        $this->assertSame('card', PaymentMethodCatalog::categoryFor('card'));
        $this->assertContains('wave', PaymentMethodCatalog::providers());
    }
}
