<?php

namespace App\Modules\Digital\Tests\Integration;

use App\Models\User;
use App\Modules\Billing\Models\Plan;
use App\Modules\Catalog\Models\Product;
use App\Modules\Digital\Exceptions\LicensePoolExhaustedException;
use App\Modules\Digital\Models\DigitalEntitlement;
use App\Modules\Digital\Models\LicensePoolKey;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Services\OrderService;
use App\Modules\Tenants\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * RC-6E — pool de licences éditeur : import (limité par plan), consommation FIFO à la vente,
 * comportement à épuisement variable selon l'abonnement (generate ⭐ / block).
 */
class LicensePoolTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private string $token;
    private Product $software;
    private OrderService $orders;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        Plan::firstOrCreate(['code' => 'starter'], ['name' => 'Starter', 'price_monthly_cents' => 0, 'price_yearly_cents' => 0, 'currency' => 'XOF', 'trial_days' => 14, 'is_active' => true, 'is_public' => true, 'sort_order' => 1]);

        $this->tenant = Tenant::create(['name' => 'Pool', 'slug' => 'pool-test', 'plan' => 'starter', 'status' => 'active', 'settings' => []]);
        $this->user = User::create(['name' => 'M', 'email' => 'm@pool.sn', 'password' => Hash::make('x'), 'tenant_id' => $this->tenant->id]);
        $this->user->assignTenantRole('manager');
        $this->token = $this->user->createToken('api')->plainTextToken;

        $this->software = Product::create([
            'tenant_id' => $this->tenant->id, 'sku' => 'SOFT', 'name' => 'Logiciel Pro', 'price_amount' => 50000,
            'price_currency' => 'XOF', 'status' => 'active', 'product_type' => Product::TYPE_DIGITAL,
            'fulfillment_type' => Product::FULFILLMENT_LICENSE,
        ]);
        $this->orders = $this->app->make(OrderService::class);
    }

    private function auth(): array
    {
        return ['Authorization' => "Bearer {$this->token}"];
    }

    private function sell(): Order
    {
        $order = $this->orders->create(['items' => [['product_id' => $this->software->id, 'quantity' => 1]]], $this->tenant->id, $this->user->id);
        $order = $this->orders->confirm($order, $this->user->id);

        return $this->orders->fulfill($order, $this->user->id);
    }

    #[Test]
    public function importing_keys_skips_duplicates_and_reports_counts(): void
    {
        $this->postJson("/api/digital/products/{$this->software->id}/license-keys", [
            'keys' => ['KEY-AAA', 'KEY-BBB', 'KEY-AAA'], // doublon dans le lot
        ], $this->auth())
            ->assertCreated()
            ->assertJsonPath('data.imported', 2)
            ->assertJsonPath('data.skipped', 1);

        // Ré-import d'une clé existante → ignorée.
        $this->postJson("/api/digital/products/{$this->software->id}/license-keys", ['keys' => ['KEY-BBB', 'KEY-CCC']], $this->auth())
            ->assertCreated()
            ->assertJsonPath('data.imported', 1)
            ->assertJsonPath('data.skipped', 1);

        $this->getJson("/api/digital/products/{$this->software->id}/license-keys/summary", $this->auth())
            ->assertOk()
            ->assertJsonPath('data.available', 3);
    }

    #[Test]
    public function a_sale_consumes_the_pool_fifo(): void
    {
        $this->postJson("/api/digital/products/{$this->software->id}/license-keys", ['keys' => ['FIRST-KEY', 'SECOND-KEY']], $this->auth())->assertCreated();

        $order = $this->sell();

        $ent = DigitalEntitlement::withoutTenantScope()->where('order_id', $order->id)->first();
        $this->assertSame('FIRST-KEY', $ent->license_key); // FIFO d'import

        $poolKey = LicensePoolKey::withoutTenantScope()->where('license_key', 'FIRST-KEY')->first();
        $this->assertSame(LicensePoolKey::STATUS_ASSIGNED, $poolKey->status);
        $this->assertSame($ent->id, $poolKey->entitlement_id);
        $this->assertSame(1, LicensePoolKey::withoutTenantScope()->where('status', 'available')->count());
    }

    #[Test]
    public function revoking_an_entitlement_releases_its_pool_key(): void
    {
        // RC-18 (D-3) — avant correctif, la clé restait `assigned` après révocation (retour/RMA) :
        // le pool fuyait → épuisement prématuré + fausses alertes pool_exhausted.
        $this->postJson("/api/digital/products/{$this->software->id}/license-keys", ['keys' => ['UNIQUE-KEY']], $this->auth())->assertCreated();

        $order = $this->sell();
        $ent   = DigitalEntitlement::withoutTenantScope()->where('order_id', $order->id)->first();
        $this->assertSame('UNIQUE-KEY', $ent->license_key);

        app(\App\Modules\Digital\Services\DigitalService::class)
            ->revokeDownToActive($this->tenant->id, $ent->order_line_id, 0);

        // La clé redevient disponible (réassignable FIFO), plus rattachée à l'accès révoqué.
        $poolKey = LicensePoolKey::withoutTenantScope()->where('license_key', 'UNIQUE-KEY')->first();
        $this->assertSame(LicensePoolKey::STATUS_AVAILABLE, $poolKey->status);
        $this->assertNull($poolKey->entitlement_id);

        // Une nouvelle vente la reconsomme (pas de génération fallback).
        $order2 = $this->sell();
        $ent2   = DigitalEntitlement::withoutTenantScope()->where('order_id', $order2->id)->first();
        $this->assertSame('UNIQUE-KEY', $ent2->license_key);
    }

    #[Test]
    public function exhausted_pool_falls_back_to_generation_by_default(): void
    {
        // Aucun import : politique par défaut `generate` → clé générée à la volée (RC-5E).
        $order = $this->sell();

        $ent = DigitalEntitlement::withoutTenantScope()->where('order_id', $order->id)->first();
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}$/', $ent->license_key);
        $this->assertSame(Order::STATUS_FULFILLED, $order->fresh()->status);
    }

    #[Test]
    public function exhausted_pool_blocks_the_delivery_when_the_tenant_policy_says_so(): void
    {
        // Surcharge tenant : block (l'arbitrage E rend le comportement dépendant de l'abonnement ;
        // la surcharge tenant prime sur la config par plan).
        $this->tenant->update(['settings' => ['license_pool_exhaustion' => 'block']]);

        $order = $this->orders->create(['items' => [['product_id' => $this->software->id, 'quantity' => 1]]], $this->tenant->id, $this->user->id);
        $order = $this->orders->confirm($order, $this->user->id);

        try {
            $this->orders->fulfill($order, $this->user->id);
            $this->fail('LicensePoolExhaustedException attendue.');
        } catch (LicensePoolExhaustedException) {
            // attendu
        }

        // Rollback complet : la commande reste confirmée, aucun entitlement émis.
        $this->assertSame(Order::STATUS_CONFIRMED, $order->fresh()->status);
        $this->assertSame(0, DigitalEntitlement::withoutTenantScope()->count());
    }

    #[Test]
    public function the_import_limit_depends_on_the_plan(): void
    {
        // Plan starter → limite 100 (config digital.pool_import_limits.default).
        $keys = array_map(fn ($i) => "K-{$i}", range(1, 101));

        $this->postJson("/api/digital/products/{$this->software->id}/license-keys", ['keys' => $keys], $this->auth())
            ->assertStatus(422);
    }

    #[Test]
    public function a_non_license_product_rejects_imports(): void
    {
        $ebook = Product::create([
            'tenant_id' => $this->tenant->id, 'sku' => 'EBOOK', 'name' => 'Ebook', 'price_amount' => 10000,
            'price_currency' => 'XOF', 'status' => 'active', 'product_type' => Product::TYPE_DIGITAL,
            'fulfillment_type' => Product::FULFILLMENT_DOWNLOAD,
        ]);

        $this->postJson("/api/digital/products/{$ebook->id}/license-keys", ['keys' => ['X']], $this->auth())
            ->assertStatus(422);
    }
}
