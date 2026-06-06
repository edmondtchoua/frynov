<?php
namespace App\Modules\Inventory\Tests\Integration;

use App\Models\User;
use App\Modules\Billing\Models\Plan;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\FiscalPeriod;
use App\Modules\Inventory\Models\Stock;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Services\PeriodLockService;
use App\Modules\Inventory\Services\StockService;
use App\Modules\Tenants\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PeriodLockTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $admin;
    private string $token;
    private Stock $stock;
    private PeriodLockService $lockSvc;
    private StockService $stockSvc;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'admin',  'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'member', 'guard_name' => 'web']);
        Plan::firstOrCreate(['code' => Plan::CODE_STARTER], [
            'name' => 'Starter', 'price_monthly_cents' => 0, 'price_yearly_cents' => 0,
            'currency' => 'XOF', 'trial_days' => 14, 'is_active' => true, 'is_public' => true, 'sort_order' => 1,
        ]);
        $this->tenant = Tenant::create(['name' => 'T', 'slug' => 't-lock', 'plan' => 'starter', 'status' => 'active', 'settings' => ['currency' => 'XOF', 'timezone' => 'Africa/Dakar', 'locale' => 'fr']]);
        $this->admin  = User::create(['name' => 'A', 'email' => 'a@t-lock.sn', 'password' => bcrypt('x'), 'tenant_id' => $this->tenant->id]);
        $this->admin->assignTenantRole('admin');
        $this->token  = $this->admin->createToken('api')->plainTextToken;
        $wh = Warehouse::create(['tenant_id' => $this->tenant->id, 'name' => 'WH', 'code' => 'WH-T', 'is_default' => true]);
        $product = Product::withoutTenantScope()->create(['tenant_id' => $this->tenant->id, 'sku' => 'T-001', 'name' => 'T', 'price_amount' => 1000, 'price_currency' => 'XOF', 'status' => 'active', 'has_variants' => false]);
        $this->stock  = Stock::create(['tenant_id' => $this->tenant->id, 'warehouse_id' => $wh->id, 'product_id' => $product->id, 'quantity' => 100, 'reserved_quantity' => 0, 'low_stock_threshold' => 5, 'unit_cost_cents' => 5000, 'total_value_cents' => 500000]);
        $this->lockSvc  = app(PeriodLockService::class);
        $this->stockSvc = app(StockService::class);
    }

    #[Test]
    public function period_lock_blocks_new_movements(): void
    {
        $period = FiscalPeriod::create([
            'tenant_id' => $this->tenant->id, 'name' => '2025', 'type' => 'annual',
            'starts_at' => '2025-01-01', 'ends_at' => '2025-12-31', 'status' => 'open',
        ]);
        $this->lockSvc->lock($period, $this->admin->id, 'Test lock');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/verrouillée/');
        PeriodLockService::assertOperationAllowed($this->tenant->id, '2025-06-15');
    }

    #[Test]
    public function open_period_allows_movements(): void
    {
        FiscalPeriod::create([
            'tenant_id' => $this->tenant->id, 'name' => '2025', 'type' => 'annual',
            'starts_at' => '2025-01-01', 'ends_at' => '2025-12-31', 'status' => 'open',
        ]);
        // Should NOT throw
        PeriodLockService::assertOperationAllowed($this->tenant->id, '2025-06-15');
        $this->assertTrue(true);
    }

    #[Test]
    public function integrity_hash_detects_tampering(): void
    {
        $period = FiscalPeriod::create([
            'tenant_id' => $this->tenant->id, 'name' => '2025', 'type' => 'annual',
            'starts_at' => '2025-01-01', 'ends_at' => '2025-12-31', 'status' => 'open',
        ]);
        $this->lockSvc->lock($period, $this->admin->id, 'Lock');
        $this->assertTrue($this->lockSvc->verifyIntegrity($period->fresh()));

        // Simulate retroactive tampering
        DB::table('fiscal_periods')->where('id', $period->id)
            ->update(['total_value_cents_at_lock' => 999999999]);

        $this->assertFalse($this->lockSvc->verifyIntegrity($period->fresh()));
    }

    #[Test]
    public function lock_api_requires_admin_role(): void
    {
        $member = User::create(['name' => 'M', 'email' => 'm@t.sn', 'password' => bcrypt('x'), 'tenant_id' => $this->tenant->id]);
        $member->assignTenantRole('member');
        $memberToken = $member->createToken('api')->plainTextToken;

        $period = FiscalPeriod::create([
            'tenant_id' => $this->tenant->id, 'name' => '2025', 'type' => 'annual',
            'starts_at' => '2025-01-01', 'ends_at' => '2025-12-31', 'status' => 'open',
        ]);

        $this->withToken($memberToken)
            ->postJson("/api/inventory/fiscal-periods/{$period->id}/lock", ['reason' => 'test'])
            ->assertForbidden();
    }

    #[Test]
    public function can_create_and_list_fiscal_periods(): void
    {
        $this->withToken($this->token)
            ->postJson('/api/inventory/fiscal-periods', [
                'name' => 'Exercice 2025', 'type' => 'annual',
                'starts_at' => '2025-01-01', 'ends_at' => '2025-12-31',
            ])->assertStatus(201);

        $this->withToken($this->token)
            ->getJson('/api/inventory/fiscal-periods')
            ->assertOk()->assertJsonCount(1, 'data');
    }
}
