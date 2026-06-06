<?php

namespace App\Modules\Billing\Tests\Unit;

use App\Modules\Billing\Exceptions\InvalidPromoCodeException;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Promotion;
use App\Modules\Billing\Services\PromotionService;
use App\Modules\Tenants\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PromotionServiceTest extends TestCase
{
    use RefreshDatabase;

    private PromotionService $svc;
    private Plan $plan;
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc    = app(PromotionService::class);
        $this->plan   = Plan::firstOrCreate(['code' => 'starter'], [
            'name' => 'Starter', 'price_monthly_cents' => 0, 'price_yearly_cents' => 0,
            'currency' => 'XOF', 'trial_days' => 14,
            'is_active' => true, 'is_public' => true, 'sort_order' => 1,
        ]);
        $this->tenant = Tenant::create([
            'name' => 'TP', 'slug' => 'tp-promo', 'plan' => 'starter',
            'status' => 'active', 'settings' => [],
        ]);
    }

    private function promo(array $overrides = []): Promotion
    {
        return Promotion::create(array_merge([
            'code'           => 'TEST10',
            'discount_type'  => 'percent',
            'discount_value' => 10,
            'max_uses'       => 5,
            'valid_from'     => now()->subDay(),
            'valid_until'    => now()->addDay(),
            'is_active'      => true,
            'applicable_plans' => ['starter'],
        ], $overrides));
    }

    #[Test]
    public function valid_code_passes(): void
    {
        $p      = $this->promo();
        $result = $this->svc->validate('TEST10', $this->tenant, 'starter');
        $this->assertSame($p->id, $result->id);
    }

    #[Test]
    public function expired_code_throws(): void
    {
        $this->promo(['valid_until' => now()->subDay()]);
        $this->expectException(InvalidPromoCodeException::class);
        $this->svc->validate('TEST10', $this->tenant, 'starter');
    }

    #[Test]
    public function exhausted_code_throws(): void
    {
        $p = $this->promo(['max_uses' => 1]);
        $p->uses()->create([
            'tenant_id' => $this->tenant->id,
            'plan_code' => 'starter',
            'discount_cents' => 100,
        ]);

        $this->expectException(InvalidPromoCodeException::class);
        $this->svc->validate('TEST10', $this->tenant, 'starter');
    }

    #[Test]
    public function inactive_code_throws(): void
    {
        $this->promo(['is_active' => false]);
        $this->expectException(InvalidPromoCodeException::class);
        $this->svc->validate('TEST10', $this->tenant, 'starter');
    }

    #[Test]
    public function wrong_plan_throws(): void
    {
        $this->promo(['applicable_plans' => ['enterprise']]);
        $this->expectException(InvalidPromoCodeException::class);
        $this->svc->validate('TEST10', $this->tenant, 'starter');
    }

    #[Test]
    public function percent_discount_returns_final_price(): void
    {
        // applyDiscount() returns final price (original - discount), not the discount amount
        $p     = $this->promo(['discount_type' => 'percent', 'discount_value' => 20]);
        $final = $p->applyDiscount(15000);
        $this->assertSame(12000, $final); // 15000 - 20% = 12000
    }

    #[Test]
    public function fixed_discount_floors_at_zero(): void
    {
        // A fixed discount larger than the price floors at 0 (max(0, price - discount))
        $p     = $this->promo(['discount_type' => 'fixed_cents', 'discount_value' => 50000]);
        $final = $p->applyDiscount(15000);
        $this->assertSame(0, $final); // max(0, 15000 - 50000) = 0
    }
}
