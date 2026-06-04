<?php

namespace App\Modules\Billing\Tests\Unit;

use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\PlanPrice;
use App\Modules\Platform\Models\ErpModule;
use Database\Seeders\ErpModulesSeeder;
use Database\Seeders\PlanModulesSeeder;
use Database\Seeders\PlansSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PlanLocalizationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function plans_are_seeded_with_localized_market_prices_and_limits(): void
    {
        $this->seed(PlansSeeder::class);

        $this->assertDatabaseHas('plans', ['code' => Plan::CODE_ESSENTIAL, 'name' => 'Essentiel']);
        $this->assertDatabaseHas('plans', ['code' => Plan::CODE_PRO, 'name' => 'Croissance']);

        $essential = Plan::with(['prices', 'limits'])->where('code', Plan::CODE_ESSENTIAL)->firstOrFail();

        $this->assertSame(500, $essential->limits?->max_products);
        $this->assertSame(300, $essential->limits?->max_monthly_orders);
        $this->assertSame(1, $essential->limits?->max_warehouses);

        $this->assertDatabaseHas('plan_prices', [
            'plan_id' => $essential->id,
            'market_code' => 'waemu',
            'currency' => 'XOF',
            'base_amount_minor' => 990000,
            'included_users' => 2,
        ]);

        $this->assertDatabaseHas('plan_prices', [
            'plan_id' => $essential->id,
            'market_code' => 'canada',
            'currency' => 'CAD',
            'base_amount_minor' => 2500,
        ]);

        $this->assertInstanceOf(PlanPrice::class, $essential->priceForMarket('canada'));
    }

    #[Test]
    public function every_public_plan_includes_every_seeded_module(): void
    {
        $this->seed(ErpModulesSeeder::class);
        $this->seed(PlansSeeder::class);
        $this->seed(PlanModulesSeeder::class);

        $moduleCount = ErpModule::count();

        foreach ([Plan::CODE_STARTER, Plan::CODE_ESSENTIAL, Plan::CODE_PRO, Plan::CODE_ENTERPRISE] as $code) {
            $plan = Plan::where('code', $code)->firstOrFail();
            $this->assertSame($moduleCount, $plan->includedModules()->count(), "Plan {$code} should include every module.");
        }
    }
}
