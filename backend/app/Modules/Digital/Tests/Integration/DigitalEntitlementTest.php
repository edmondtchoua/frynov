<?php

namespace App\Modules\Digital\Tests\Integration;

use App\Models\User;
use App\Modules\Billing\Models\Plan;
use App\Modules\Catalog\Models\Product;
use App\Modules\Digital\Models\DigitalEntitlement;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Services\OrderService;
use App\Modules\Tenants\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * RC-5E — produits digitaux : commande sans stock (services/digital) + droits d'accès (entitlements)
 * générés à la vente, vérification par jeton, révocation, isolation tenant.
 */
class DigitalEntitlementTest extends TestCase
{
    use RefreshDatabase;

    private const CUSTOMER_ID = '33333333-3333-4333-8333-333333333333';

    private Tenant $tenant;
    private User $user;
    private string $token;
    private OrderService $orders;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        Plan::firstOrCreate(['code' => 'starter'], ['name' => 'Starter', 'price_monthly_cents' => 0, 'price_yearly_cents' => 0, 'currency' => 'XOF', 'trial_days' => 14, 'is_active' => true, 'is_public' => true, 'sort_order' => 1]);

        $this->tenant = Tenant::create(['name' => 'Dig', 'slug' => 'dig-test', 'plan' => 'starter', 'status' => 'active', 'settings' => []]);
        $this->seedCustomer(self::CUSTOMER_ID, $this->tenant->id); // RC-20 (P-5) — le customer_id doit exister
        $this->user = User::create(['name' => 'M', 'email' => 'm@dig.sn', 'password' => Hash::make('x'), 'tenant_id' => $this->tenant->id]);
        $this->user->assignTenantRole('manager');
        $this->token = $this->user->createToken('api')->plainTextToken;

        $this->orders = $this->app->make(OrderService::class);
    }

    private function auth(): array
    {
        return ['Authorization' => "Bearer {$this->token}"];
    }

    private function digitalProduct(string $fulfillment = Product::FULFILLMENT_DOWNLOAD): Product
    {
        return Product::create([
            'tenant_id' => $this->tenant->id, 'sku' => 'EBOOK-' . substr(md5($fulfillment), 0, 5), 'name' => 'Ebook PHP',
            'price_amount' => 10000, 'price_currency' => 'XOF', 'status' => 'active',
            'product_type' => Product::TYPE_DIGITAL, 'fulfillment_type' => $fulfillment,
        ]); // stock_tracking dérivé = none
    }

    private function sell(Product $p, int $qty = 1): Order
    {
        $order = $this->orders->create([
            'customer_id' => self::CUSTOMER_ID,
            'items'       => [['product_id' => $p->id, 'quantity' => $qty]],
        ], $this->tenant->id, $this->user->id);
        $order = $this->orders->confirm($order, $this->user->id);

        return $this->orders->fulfill($order, $this->user->id);
    }

    #[Test]
    public function a_digital_product_is_orderable_without_stock_and_grants_an_entitlement(): void
    {
        $ebook = $this->digitalProduct();

        // Aucun stock seedé : confirm + fulfill ne doivent PAS lever InsufficientStockException.
        $order = $this->sell($ebook);
        $this->assertSame(Order::STATUS_FULFILLED, $order->fresh()->status);

        $ent = DigitalEntitlement::withoutTenantScope()->where('order_id', $order->id)->first();
        $this->assertNotNull($ent);
        $this->assertSame(DigitalEntitlement::STATUS_ACTIVE, $ent->status);
        $this->assertSame(self::CUSTOMER_ID, $ent->customer_id);
        $this->assertSame(Product::FULFILLMENT_DOWNLOAD, $ent->fulfillment_type);
        $this->assertNotNull($ent->access_token);
        $this->assertNull($ent->license_key); // download → pas de clé
    }

    #[Test]
    public function a_license_product_grants_a_license_key(): void
    {
        $license = $this->digitalProduct(Product::FULFILLMENT_LICENSE);
        $order   = $this->sell($license);

        $ent = DigitalEntitlement::withoutTenantScope()->where('order_id', $order->id)->first();
        $this->assertSame(Product::FULFILLMENT_LICENSE, $ent->fulfillment_type);
        $this->assertNotNull($ent->license_key);
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}$/', $ent->license_key);
    }

    #[Test]
    public function a_service_is_orderable_without_stock_and_grants_no_entitlement(): void
    {
        $service = Product::create([
            'tenant_id' => $this->tenant->id, 'sku' => 'SRV-1', 'name' => 'Installation', 'price_amount' => 20000,
            'price_currency' => 'XOF', 'status' => 'active', 'product_type' => Product::TYPE_SERVICE,
        ]); // stock_tracking=none, fulfillment=manual

        $order = $this->sell($service);
        $this->assertSame(Order::STATUS_FULFILLED, $order->fresh()->status);
        $this->assertSame(0, DigitalEntitlement::withoutTenantScope()->where('order_id', $order->id)->count());
    }

    #[Test]
    public function the_access_endpoint_grants_then_refuses_after_revocation(): void
    {
        $license = $this->digitalProduct(Product::FULFILLMENT_LICENSE);
        $order   = $this->sell($license);
        $ent     = DigitalEntitlement::withoutTenantScope()->where('order_id', $order->id)->first();

        // Accès actif → 200 + secret révélé.
        $this->getJson("/api/digital/access/{$ent->access_token}", $this->auth())
            ->assertOk()
            ->assertJsonPath('data.license_key', $ent->license_key)
            ->assertJsonPath('data.access_token', $ent->access_token);

        // Révocation (manager).
        $this->postJson("/api/digital/entitlements/{$ent->id}/revoke", [], $this->auth())
            ->assertOk()
            ->assertJsonPath('data.status', DigitalEntitlement::STATUS_REVOKED);

        // Accès révoqué → 403.
        $this->getJson("/api/digital/access/{$ent->access_token}", $this->auth())
            ->assertStatus(403);
    }

    #[Test]
    public function the_access_token_is_isolated_per_tenant(): void
    {
        $ebook = $this->digitalProduct();
        $order = $this->sell($ebook);
        $ent   = DigitalEntitlement::withoutTenantScope()->where('order_id', $order->id)->first();

        $other = Tenant::create(['name' => 'Other', 'slug' => 'other-dig', 'plan' => 'starter', 'status' => 'active', 'settings' => []]);
        $otherUser = User::create(['name' => 'O', 'email' => 'o@dig.sn', 'password' => Hash::make('x'), 'tenant_id' => $other->id]);
        $otherUser->assignTenantRole('manager');
        $otherToken = $otherUser->createToken('api')->plainTextToken;

        // Le jeton d'un autre tenant ne doit pas être résolu (404, pas de fuite de secret).
        $this->getJson("/api/digital/access/{$ent->access_token}", ['Authorization' => "Bearer {$otherToken}"])
            ->assertStatus(404);
    }

    #[Test]
    public function the_order_entitlements_endpoint_lists_without_secrets(): void
    {
        $ebook = $this->digitalProduct(Product::FULFILLMENT_LICENSE);
        $order = $this->sell($ebook);

        $res = $this->getJson("/api/digital/orders/{$order->id}/entitlements", $this->auth())
            ->assertOk()
            ->assertJsonPath('count', 1);

        // La liste scopée commande n'expose ni le jeton ni la clé.
        $row = $res->json('data.0');
        $this->assertArrayNotHasKey('access_token', $row);
        $this->assertArrayNotHasKey('license_key', $row);
        $this->assertSame('Ebook PHP', $row['product_name']);
    }

    #[Test]
    public function a_physical_product_grants_no_entitlement(): void
    {
        $phys = Product::create([
            'tenant_id' => $this->tenant->id, 'sku' => 'MUG', 'name' => 'Mug', 'price_amount' => 3000,
            'price_currency' => 'XOF', 'status' => 'active', 'product_type' => Product::TYPE_SIMPLE,
        ]);
        $stock = $this->app->make(\App\Modules\Inventory\Services\StockService::class)->findOrCreate($this->tenant->id, $phys->id, null);
        $this->app->make(\App\Modules\Inventory\Services\StockService::class)->moveIn($stock, 10);

        $order = $this->sell($phys);
        $this->assertSame(0, DigitalEntitlement::withoutTenantScope()->where('order_id', $order->id)->count());
    }
}
