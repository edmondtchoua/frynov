<?php

namespace App\Modules\Digital\Tests\Integration;

use App\Models\User;
use App\Modules\Billing\Models\Plan;
use App\Modules\Catalog\Models\Product;
use App\Modules\Digital\Models\DigitalEntitlement;
use App\Modules\Digital\Models\LicensePoolKey;
use App\Modules\Digital\Services\DigitalService;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Services\OrderService;
use App\Modules\Tenants\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * RC-7D — un droit d'accès PAR EXEMPLAIRE. Une ligne de qty N accorde N entitlements distincts
 * (jeton/clé chacun, rang `unit_index` 1..N), au lieu d'un seul par ligne. Idempotence préservée.
 */
class PerUnitEntitlementTest extends TestCase
{
    use RefreshDatabase;

    private const CUSTOMER_ID = '44444444-4444-4444-8444-444444444444';

    private Tenant $tenant;
    private User $user;
    private OrderService $orders;
    private DigitalService $digital;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        Plan::firstOrCreate(['code' => 'starter'], ['name' => 'Starter', 'price_monthly_cents' => 0, 'price_yearly_cents' => 0, 'currency' => 'XOF', 'trial_days' => 14, 'is_active' => true, 'is_public' => true, 'sort_order' => 1]);

        $this->tenant = Tenant::create(['name' => 'Unit', 'slug' => 'unit-test', 'plan' => 'starter', 'status' => 'active', 'settings' => []]);
        $this->seedCustomer(self::CUSTOMER_ID, $this->tenant->id); // RC-20 (P-5) — le customer_id doit exister
        $this->user = User::create(['name' => 'M', 'email' => 'm@unit.sn', 'password' => Hash::make('x'), 'tenant_id' => $this->tenant->id]);
        $this->user->assignTenantRole('manager');

        $this->orders  = $this->app->make(OrderService::class);
        $this->digital = $this->app->make(DigitalService::class);
    }

    private function digitalProduct(string $fulfillment = Product::FULFILLMENT_DOWNLOAD): Product
    {
        return Product::create([
            'tenant_id' => $this->tenant->id, 'sku' => 'DIG-' . substr(md5($fulfillment . microtime()), 0, 6), 'name' => 'Digital',
            'price_amount' => 10000, 'price_currency' => 'XOF', 'status' => 'active',
            'product_type' => Product::TYPE_DIGITAL, 'fulfillment_type' => $fulfillment,
        ]);
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

    private function entitlements(Order $order): \Illuminate\Support\Collection
    {
        return DigitalEntitlement::withoutTenantScope()
            ->where('order_id', $order->id)
            ->orderBy('unit_index')
            ->get();
    }

    #[Test]
    public function a_quantity_of_three_grants_three_distinct_accesses(): void
    {
        $ebook = $this->digitalProduct();
        $order = $this->sell($ebook, 3);

        $ents = $this->entitlements($order);
        $this->assertCount(3, $ents);
        // Rangs 1,2,3 et jetons tous distincts.
        $this->assertSame([1, 2, 3], $ents->pluck('unit_index')->all());
        $this->assertCount(3, $ents->pluck('access_token')->unique());
        $ents->each(fn ($e) => $this->assertSame(DigitalEntitlement::STATUS_ACTIVE, $e->status));
    }

    #[Test]
    public function each_licensed_unit_consumes_one_pool_key(): void
    {
        $software = $this->digitalProduct(Product::FULFILLMENT_LICENSE);
        // Pool de 3 clés éditeur → une par exemplaire, FIFO.
        $this->digital->importPoolKeys($this->tenant->id, $software, ['AAAA-1111', 'BBBB-2222', 'CCCC-3333'], $this->user->id);

        $order = $this->sell($software, 3);

        $ents = $this->entitlements($order);
        $this->assertCount(3, $ents);
        // Les 3 clés du pool sont servies (FIFO), chacune sur un accès distinct.
        $this->assertEqualsCanonicalizing(['AAAA-1111', 'BBBB-2222', 'CCCC-3333'], $ents->pluck('license_key')->all());
        $this->assertSame(3, LicensePoolKey::withoutTenantScope()
            ->where('product_id', $software->id)
            ->where('status', LicensePoolKey::STATUS_ASSIGNED)
            ->count());
        $this->assertSame(0, LicensePoolKey::withoutTenantScope()
            ->where('product_id', $software->id)
            ->where('status', LicensePoolKey::STATUS_AVAILABLE)
            ->count());
    }

    #[Test]
    public function re_issuing_for_an_already_fulfilled_order_creates_no_duplicate(): void
    {
        $ebook = $this->digitalProduct();
        $order = $this->sell($ebook, 3);
        $this->assertCount(3, $this->entitlements($order));

        // Rejouer l'émission ne doit rien ajouter (idempotence par exemplaire).
        $this->digital->issueForOrder($order->fresh('lines'), $this->user->id);
        $this->assertCount(3, $this->entitlements($order));
    }

    #[Test]
    public function a_single_unit_still_grants_exactly_one_access(): void
    {
        // Non-régression : qty 1 → 1 accès, rang 1 (comportement RC-5E inchangé).
        $ebook = $this->digitalProduct();
        $order = $this->sell($ebook, 1);

        $ents = $this->entitlements($order);
        $this->assertCount(1, $ents);
        $this->assertSame(1, $ents->first()->unit_index);
    }
}
