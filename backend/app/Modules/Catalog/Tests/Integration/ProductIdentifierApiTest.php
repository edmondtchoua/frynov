<?php
namespace App\Modules\Catalog\Tests\Integration;

use App\Models\User;
use App\Modules\Billing\Models\Plan;
use App\Modules\Tenants\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProductIdentifierApiTest extends TestCase
{
    use RefreshDatabase;
    private Tenant $tenant;
    private User $admin;
    private string $adminToken;
    protected function setUp(): void {
        parent::setUp();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        Plan::firstOrCreate(['code' => 'starter'], ['name' => 'Starter', 'price_monthly_cents' => 0, 'price_yearly_cents' => 0, 'currency' => 'XOF', 'trial_days' => 14, 'is_active' => true, 'is_public' => true, 'sort_order' => 1]);
        $this->tenant = Tenant::create(['name' => 'T', 'slug' => 'pid-api', 'plan' => 'starter', 'status' => 'active', 'settings' => []]);
        $this->admin  = User::create(['name' => 'A', 'email' => 'a@pid-api.sn', 'password' => bcrypt('x'), 'tenant_id' => $this->tenant->id]);
        $this->admin->assignTenantRole('admin');
        $this->adminToken = $this->admin->createToken('api')->plainTextToken;
    }
    #[Test] public function product_gets_auto_sku(): void {
        $r = $this->withToken($this->adminToken)->postJson('/api/catalog/products', ['name' => 'P1', 'price_amount' => 1000, 'price_currency' => 'XOF', 'status' => 'draft'])->assertStatus(201);
        $this->assertNotEmpty($r->json('data.sku'));
        $this->assertStringContainsString('PROD', $r->json('data.sku'));
    }
    #[Test] public function product_gets_auto_internal_barcode(): void {
        $r = $this->withToken($this->adminToken)->postJson('/api/catalog/products', ['name' => 'P2', 'price_amount' => 1000, 'price_currency' => 'XOF', 'status' => 'draft'])->assertStatus(201);
        $this->assertStringStartsWith('FRY', $r->json('data.internal_barcode') ?? '');
    }
    #[Test] public function two_products_unique_identifiers(): void {
        $r1 = $this->withToken($this->adminToken)->postJson('/api/catalog/products', ['name' => 'PA', 'price_amount' => 1000, 'price_currency' => 'XOF', 'status' => 'draft'])->assertStatus(201);
        $r2 = $this->withToken($this->adminToken)->postJson('/api/catalog/products', ['name' => 'PB', 'price_amount' => 2000, 'price_currency' => 'XOF', 'status' => 'draft'])->assertStatus(201);
        $this->assertNotEquals($r1->json('data.sku'), $r2->json('data.sku'));
        $this->assertNotEquals($r1->json('data.internal_barcode'), $r2->json('data.internal_barcode'));
    }
    #[Test] public function custom_sku_accepted(): void {
        $r = $this->withToken($this->adminToken)->postJson('/api/catalog/products', ['name' => 'PC', 'sku' => 'CUSTOM-001', 'price_amount' => 1000, 'price_currency' => 'XOF', 'status' => 'draft'])->assertStatus(201);
        $this->assertSame('CUSTOM-001', $r->json('data.sku'));
    }
    #[Test] public function duplicate_sku_rejected(): void {
        $this->withToken($this->adminToken)->postJson('/api/catalog/products', ['name' => 'PD1', 'sku' => 'DUP-001', 'price_amount' => 1000, 'price_currency' => 'XOF', 'status' => 'draft'])->assertStatus(201);
        $this->withToken($this->adminToken)->postJson('/api/catalog/products', ['name' => 'PD2', 'sku' => 'DUP-001', 'price_amount' => 2000, 'price_currency' => 'XOF', 'status' => 'draft'])->assertStatus(422);
    }
    #[Test] public function valid_gtin_accepted(): void {
        // 3700123456780: valid EAN-13 (check digit = 0 matches)
        $this->withToken($this->adminToken)->postJson('/api/catalog/products', ['name' => 'PG1', 'gtin' => '3700123456780', 'price_amount' => 1000, 'price_currency' => 'XOF', 'status' => 'draft'])->assertStatus(201);
    }
    #[Test] public function invalid_gtin_rejected(): void {
        // 3700123456789: invalid EAN-13 (check digit should be 0, not 9)
        $this->withToken($this->adminToken)->postJson('/api/catalog/products', ['name' => 'PG2', 'gtin' => '3700123456789', 'price_amount' => 1000, 'price_currency' => 'XOF', 'status' => 'draft'])->assertStatus(422);
    }
}
