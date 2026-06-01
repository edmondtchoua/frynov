<?php

namespace App\Modules\Billing\Tests\Unit;

use App\Models\User;
use App\Modules\Billing\Exceptions\QuotaExceededException;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Services\QuotaService;
use App\Modules\Catalog\Models\Product;
use App\Modules\Orders\Models\Order;
use App\Modules\Tenants\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class QuotaServiceTest extends TestCase
{
    use RefreshDatabase;

    private QuotaService $service;
    private Tenant $tenant;
    private Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = $this->app->make(QuotaService::class);

        $this->tenant = Tenant::create([
            'name'   => 'Quota Corp',
            'slug'   => 'quota-corp',
            'plan'   => Plan::CODE_STARTER,
            'status' => 'active',
        ]);

        $this->plan = Plan::create([
            'code'                => Plan::CODE_STARTER,
            'name'                => 'Starter',
            'price_monthly_cents' => 0,
            'price_yearly_cents'  => 0,
            'currency'            => 'XOF',
            'max_users'           => 3,
            'max_products'        => 10,
            'max_monthly_orders'  => 50,
            'trial_days'          => 14,
            'is_active'           => true,
            'is_public'           => true,
            'sort_order'          => 1,
        ]);

        Subscription::create([
            'tenant_id'            => $this->tenant->id,
            'plan_id'              => $this->plan->id,
            'status'               => Subscription::STATUS_ACTIVE,
            'current_period_start' => now(),
            'current_period_end'   => now()->addMonth(),
        ]);
    }

    // ── limitFor ────────────────────────────────────────────────────────────────

    #[Test]
    public function limit_for_returns_plan_field_values(): void
    {
        $this->assertSame(3,  $this->service->limitFor($this->tenant, QuotaService::RESOURCE_USERS));
        $this->assertSame(10, $this->service->limitFor($this->tenant, QuotaService::RESOURCE_PRODUCTS));
        $this->assertSame(50, $this->service->limitFor($this->tenant, QuotaService::RESOURCE_ORDERS));
    }

    #[Test]
    public function limit_for_returns_null_when_plan_field_is_zero(): void
    {
        $this->plan->update(['max_users' => 0]);

        $this->assertNull($this->service->limitFor($this->tenant, QuotaService::RESOURCE_USERS));
    }

    #[Test]
    public function limit_for_returns_null_when_plan_field_is_null(): void
    {
        $this->plan->update(['max_products' => null]);

        $this->assertNull($this->service->limitFor($this->tenant, QuotaService::RESOURCE_PRODUCTS));
    }

    #[Test]
    public function limit_for_returns_null_for_trialing_subscription(): void
    {
        Subscription::where('tenant_id', $this->tenant->id)
            ->update(['status' => Subscription::STATUS_TRIALING]);

        // Trialing is still active — limits must be read from the plan
        $this->assertSame(3, $this->service->limitFor($this->tenant, QuotaService::RESOURCE_USERS));
    }

    #[Test]
    public function limit_for_returns_null_when_no_active_subscription(): void
    {
        Subscription::where('tenant_id', $this->tenant->id)
            ->update(['status' => Subscription::STATUS_CANCELLED]);

        $this->assertNull($this->service->limitFor($this->tenant, QuotaService::RESOURCE_USERS));
    }

    #[Test]
    public function limit_for_returns_null_for_unknown_resource(): void
    {
        $this->assertNull($this->service->limitFor($this->tenant, 'invoices'));
    }

    // ── usageFor ────────────────────────────────────────────────────────────────

    #[Test]
    public function usage_for_users_counts_tenant_users(): void
    {
        $this->createUser('a@test.com');
        $this->createUser('b@test.com');

        $this->assertSame(2, $this->service->usageFor($this->tenant, QuotaService::RESOURCE_USERS));
    }

    #[Test]
    public function usage_for_users_ignores_other_tenant_users(): void
    {
        $other = Tenant::create(['name' => 'Other', 'slug' => 'other', 'status' => 'active']);
        $this->createUser('other@test.com', $other);
        $this->createUser('mine@test.com');

        $this->assertSame(1, $this->service->usageFor($this->tenant, QuotaService::RESOURCE_USERS));
    }

    #[Test]
    public function usage_for_products_counts_tenant_products(): void
    {
        $this->createProduct('SKU-1');
        $this->createProduct('SKU-2');
        $this->createProduct('SKU-3');

        $this->assertSame(3, $this->service->usageFor($this->tenant, QuotaService::RESOURCE_PRODUCTS));
    }

    #[Test]
    public function usage_for_products_ignores_other_tenant_products(): void
    {
        $other = Tenant::create(['name' => 'Other', 'slug' => 'other', 'status' => 'active']);
        $this->createProduct('SKU-OTHER', $other);
        $this->createProduct('SKU-MINE');

        $this->assertSame(1, $this->service->usageFor($this->tenant, QuotaService::RESOURCE_PRODUCTS));
    }

    #[Test]
    public function usage_for_orders_counts_current_month_only(): void
    {
        $this->createOrder('ORD-001');
        $this->createOrder('ORD-002');

        // An order from last month must NOT be counted
        DB::table('orders')->insert([
            'id'           => (string) Str::uuid(),
            'tenant_id'    => (string) $this->tenant->id,
            'number'       => 'ORD-OLD',
            'status'       => 'confirmed',
            'total_amount' => 0,
            'currency'     => 'XOF',
            'created_at'   => now()->subMonth()->toDateTimeString(),
            'updated_at'   => now()->subMonth()->toDateTimeString(),
        ]);

        $this->assertSame(2, $this->service->usageFor($this->tenant, QuotaService::RESOURCE_ORDERS));
    }

    #[Test]
    public function usage_for_orders_ignores_other_tenant_orders(): void
    {
        $other = Tenant::create(['name' => 'Other', 'slug' => 'other', 'status' => 'active']);
        $this->createOrder('ORD-OTHER', $other);
        $this->createOrder('ORD-MINE');

        $this->assertSame(1, $this->service->usageFor($this->tenant, QuotaService::RESOURCE_ORDERS));
    }

    #[Test]
    public function usage_for_returns_zero_for_unknown_resource(): void
    {
        $this->assertSame(0, $this->service->usageFor($this->tenant, 'invoices'));
    }

    // ── check ───────────────────────────────────────────────────────────────────

    #[Test]
    public function check_passes_when_below_user_limit(): void
    {
        $this->createUser('a@test.com');
        $this->createUser('b@test.com'); // 2 of 3

        $this->expectNotToPerformAssertions();
        $this->service->check($this->tenant, QuotaService::RESOURCE_USERS);
    }

    #[Test]
    public function check_throws_when_user_limit_reached(): void
    {
        $this->createUser('a@test.com');
        $this->createUser('b@test.com');
        $this->createUser('c@test.com'); // 3 of 3 — at limit

        $this->expectException(QuotaExceededException::class);
        $this->service->check($this->tenant, QuotaService::RESOURCE_USERS);
    }

    #[Test]
    public function check_throws_when_product_limit_reached(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            $this->createProduct("SKU-{$i}");
        }

        $this->expectException(QuotaExceededException::class);
        $this->service->check($this->tenant, QuotaService::RESOURCE_PRODUCTS);
    }

    #[Test]
    public function check_throws_when_monthly_order_limit_reached(): void
    {
        $this->plan->update(['max_monthly_orders' => 2]);
        $this->createOrder('ORD-001');
        $this->createOrder('ORD-002'); // 2 of 2 — at limit

        $this->expectException(QuotaExceededException::class);
        $this->service->check($this->tenant, QuotaService::RESOURCE_ORDERS);
    }

    #[Test]
    public function check_passes_when_limit_is_zero_unlimited(): void
    {
        $this->plan->update(['max_users' => 0]); // 0 = unlimited

        for ($i = 1; $i <= 5; $i++) {
            $this->createUser("u{$i}@test.com");
        }

        $this->expectNotToPerformAssertions();
        $this->service->check($this->tenant, QuotaService::RESOURCE_USERS);
    }

    #[Test]
    public function check_passes_when_no_active_subscription(): void
    {
        Subscription::where('tenant_id', $this->tenant->id)
            ->update(['status' => Subscription::STATUS_CANCELLED]);

        for ($i = 1; $i <= 10; $i++) {
            $this->createUser("u{$i}@test.com");
        }

        $this->expectNotToPerformAssertions();
        $this->service->check($this->tenant, QuotaService::RESOURCE_USERS);
    }

    #[Test]
    public function exception_carries_resource_limit_and_usage(): void
    {
        $this->plan->update(['max_products' => 2]);
        $this->createProduct('SKU-1');
        $this->createProduct('SKU-2');

        try {
            $this->service->check($this->tenant, QuotaService::RESOURCE_PRODUCTS);
            $this->fail('Expected QuotaExceededException was not thrown.');
        } catch (QuotaExceededException $e) {
            $this->assertSame(QuotaService::RESOURCE_PRODUCTS, $e->resource);
            $this->assertSame(2, $e->limit);
            $this->assertSame(2, $e->usage);
        }
    }

    // ── isWithinLimit ───────────────────────────────────────────────────────────

    #[Test]
    public function is_within_limit_returns_true_when_below_limit(): void
    {
        $this->createProduct('SKU-1'); // 1 of 10

        $this->assertTrue($this->service->isWithinLimit($this->tenant, QuotaService::RESOURCE_PRODUCTS));
    }

    #[Test]
    public function is_within_limit_returns_false_when_at_limit(): void
    {
        $this->plan->update(['max_products' => 1]);
        $this->createProduct('SKU-1'); // 1 of 1 — at limit

        $this->assertFalse($this->service->isWithinLimit($this->tenant, QuotaService::RESOURCE_PRODUCTS));
    }

    #[Test]
    public function is_within_limit_returns_true_when_unlimited(): void
    {
        $this->plan->update(['max_users' => 0]);

        for ($i = 1; $i <= 20; $i++) {
            $this->createUser("u{$i}@test.com");
        }

        $this->assertTrue($this->service->isWithinLimit($this->tenant, QuotaService::RESOURCE_USERS));
    }

    // ── Helpers ─────────────────────────────────────────────────────────────────

    private function createUser(string $email, ?Tenant $tenant = null): User
    {
        return User::create([
            'tenant_id' => ($tenant ?? $this->tenant)->id,
            'name'      => 'Test User',
            'email'     => $email,
            'password'  => bcrypt('secret'),
        ]);
    }

    private function createProduct(string $sku, ?Tenant $tenant = null): Product
    {
        return Product::create([
            'tenant_id'    => ($tenant ?? $this->tenant)->id,
            'sku'          => $sku,
            'name'         => "Product {$sku}",
            'price_amount' => 1000,
        ]);
    }

    private function createOrder(string $number, ?Tenant $tenant = null): Order
    {
        return Order::create([
            'tenant_id'    => ($tenant ?? $this->tenant)->id,
            'number'       => $number,
            'status'       => 'confirmed',
            'total_amount' => 0,
            'currency'     => 'XOF',
        ]);
    }
}
