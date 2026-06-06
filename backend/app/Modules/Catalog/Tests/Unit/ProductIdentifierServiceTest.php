<?php
namespace App\Modules\Catalog\Tests\Unit;

use App\Modules\Billing\Models\Plan;
use App\Modules\Catalog\Services\ProductIdentifierService;
use App\Modules\Tenants\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProductIdentifierServiceTest extends TestCase
{
    use RefreshDatabase;
    private ProductIdentifierService $svc;
    private Tenant $tenantA;
    private Tenant $tenantB;
    protected function setUp(): void {
        parent::setUp();
        Plan::firstOrCreate(['code' => 'starter'], ['name' => 'Starter', 'price_monthly_cents' => 0, 'price_yearly_cents' => 0, 'currency' => 'XOF', 'trial_days' => 14, 'is_active' => true, 'is_public' => true, 'sort_order' => 1]);
        $this->svc     = app(ProductIdentifierService::class);
        $this->tenantA = Tenant::create(['name' => 'A', 'slug' => 'pid-a', 'plan' => 'starter', 'status' => 'active', 'settings' => []]);
        $this->tenantB = Tenant::create(['name' => 'B', 'slug' => 'pid-b', 'plan' => 'starter', 'status' => 'active', 'settings' => []]);
    }
    #[Test] public function generates_sequential_skus(): void {
        $s1 = $this->svc->generateSku($this->tenantA->id);
        $s2 = $this->svc->generateSku($this->tenantA->id);
        $this->assertNotEquals($s1, $s2);
        $this->assertStringContainsString('PROD', $s1);
    }
    #[Test] public function uses_category_prefix(): void {
        $s = $this->svc->generateSku($this->tenantA->id, 'Vetements');
        $this->assertStringStartsWith('VETE', $s);
    }
    #[Test] public function tenants_are_isolated(): void {
        $a = $this->svc->generateSku($this->tenantA->id);
        $b = $this->svc->generateSku($this->tenantB->id);
        $this->assertStringEndsWith('000001', $a);
        $this->assertStringEndsWith('000001', $b);
    }
    #[Test] public function generates_internal_barcode(): void {
        $bc = $this->svc->generateInternalBarcode($this->tenantA->id);
        $this->assertStringStartsWith('FRY', $bc);
        $this->assertSame(13, strlen($bc));
    }
    #[Test] public function barcodes_are_sequential(): void {
        $b1 = $this->svc->generateInternalBarcode($this->tenantA->id);
        $b2 = $this->svc->generateInternalBarcode($this->tenantA->id);
        $this->assertSame((int)substr($b1, 3) + 1, (int)substr($b2, 3));
    }
    #[Test] public function no_duplicates_in_10_calls(): void {
        $skus = [];
        for ($i=0; $i<10; $i++) $skus[] = $this->svc->generateSku($this->tenantA->id);
        $this->assertCount(10, array_unique($skus));
    }
    #[Test] public function valid_ean13_passes(): void {
        // 3700123456780: digits 3700123456780, sum=100, expected check=0, provided=0 ✓
        $this->svc->validateGtin('3700123456780');
        $this->assertTrue(true);
    }
    #[Test] public function invalid_gtin_check_digit_throws(): void {
        // 3700123456789: sum=100, expected check=0, provided=9 → invalid ✓
        $this->expectException(\InvalidArgumentException::class);
        $this->svc->validateGtin('3700123456789');
    }
    #[Test] public function invalid_gtin_length_throws(): void {
        $this->expectException(\InvalidArgumentException::class);
        $this->svc->validateGtin('123');
    }
}
