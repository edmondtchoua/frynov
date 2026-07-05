<?php

namespace App\Modules\Catalog\Tests\Integration;

use App\Models\User;
use App\Modules\Billing\Models\Plan;
use App\Modules\Catalog\Models\Product;
use App\Modules\Tenants\Models\Tenant;
use App\Modules\Warranties\Models\WarrantyPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * RC-5K — la politique produit complète (type / stock / livraison / garantie) est pilotable via
 * l'API catalogue : warranty_policy_id validé (appartenance tenant) et exposé par la Resource.
 */
class ProductWarrantyPolicyApiTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private string $token;
    private WarrantyPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        Plan::firstOrCreate(['code' => 'starter'], ['name' => 'Starter', 'price_monthly_cents' => 0, 'price_yearly_cents' => 0, 'currency' => 'XOF', 'trial_days' => 14, 'is_active' => true, 'is_public' => true, 'sort_order' => 1]);

        $this->tenant = Tenant::create(['name' => 'Pol', 'slug' => 'pol-api', 'plan' => 'starter', 'status' => 'active', 'settings' => []]);
        $user = User::create(['name' => 'M', 'email' => 'm@pol.sn', 'password' => Hash::make('x'), 'tenant_id' => $this->tenant->id]);
        $user->assignTenantRole('manager');
        $this->token = $user->createToken('api')->plainTextToken;

        $this->policy = WarrantyPolicy::create([
            'tenant_id' => $this->tenant->id, 'name' => 'G12', 'duration_months' => 12, 'is_active' => true,
        ]);
    }

    private function auth(): array
    {
        return ['Authorization' => "Bearer {$this->token}"];
    }

    #[Test]
    public function creating_a_product_with_full_policy_persists_and_exposes_it(): void
    {
        $res = $this->postJson('/api/catalog/products', [
            'name' => 'iPhone 15', 'price_amount' => 800000, 'price_currency' => 'XOF',
            'product_type' => 'simple', 'stock_tracking' => 'serialized', 'fulfillment_type' => 'delivery',
            'warranty_policy_id' => $this->policy->id,
        ], $this->auth())->assertCreated();

        $res->assertJsonPath('data.stock_tracking', 'serialized')
            ->assertJsonPath('data.warranty_policy_id', $this->policy->id)
            ->assertJsonPath('data.is_serialized', true);

        $this->assertSame($this->policy->id, Product::withoutTenantScope()
            ->where('tenant_id', $this->tenant->id)->first()->warranty_policy_id);
    }

    #[Test]
    public function a_policy_of_another_tenant_is_rejected(): void
    {
        $other = Tenant::create(['name' => 'Other', 'slug' => 'other-pol', 'plan' => 'starter', 'status' => 'active', 'settings' => []]);
        $foreign = WarrantyPolicy::create(['tenant_id' => $other->id, 'name' => 'X', 'duration_months' => 6, 'is_active' => true]);

        $this->postJson('/api/catalog/products', [
            'name' => 'Mug', 'price_amount' => 3000, 'price_currency' => 'XOF',
            'warranty_policy_id' => $foreign->id,
        ], $this->auth())->assertStatus(422);
    }

    #[Test]
    public function updating_detaches_the_policy_with_null(): void
    {
        $create = $this->postJson('/api/catalog/products', [
            'name' => 'Blender', 'price_amount' => 30000, 'price_currency' => 'XOF',
            'warranty_policy_id' => $this->policy->id,
        ], $this->auth())->assertCreated();

        $id = $create->json('data.id');

        $this->putJson("/api/catalog/products/{$id}", ['warranty_policy_id' => null], $this->auth())
            ->assertOk()
            ->assertJsonPath('data.warranty_policy_id', null);
    }
}
