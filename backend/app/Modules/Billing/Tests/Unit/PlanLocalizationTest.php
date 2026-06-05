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
    public function paid_plans_do_not_hard_cap_seats_only_the_free_tier_does(): void
    {
        $this->seed(PlansSeeder::class);

        // Free Découverte tier keeps a hard 1-user cap to nudge the upgrade.
        $this->assertSame(1, Plan::where('code', Plan::CODE_STARTER)->firstOrFail()->max_users);

        // Paid plans: seats are a SOFT "included" guideline, NEVER a hard cap — a
        // growing business must not be blocked from inviting members. (Regression:
        // Business previously dead-ended at 10 users with a misleading "upgrade" msg.)
        foreach ([Plan::CODE_ESSENTIAL, Plan::CODE_PRO, Plan::CODE_ENTERPRISE] as $code) {
            $plan = Plan::where('code', $code)->firstOrFail();
            $this->assertNull($plan->max_users, "Paid plan {$code} must not hard-cap users.");
            $this->assertNull($plan->max_agents, "Paid plan {$code} must not hard-cap agents.");
        }

        // included_users is preserved on the localized prices for display /
        // future per-seat overage billing.
        $business = Plan::where('code', Plan::CODE_ENTERPRISE)->firstOrFail();
        $this->assertDatabaseHas('plan_prices', ['plan_id' => $business->id, 'included_users' => 10]);
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

    #[Test]
    public function plan_price_migration_uses_mysql_safe_index_lengths(): void
    {
        $migration = file_get_contents(base_path('app/Modules/Billing/database/migrations/2026_06_04_000001_create_plan_prices_table.php'));

        $this->assertStringContainsString("string('market_code', 32)", $migration);
        $this->assertStringContainsString("string('country_code', 2)", $migration);
        $this->assertStringContainsString("string('interval', 16)", $migration);
        $this->assertStringContainsString('plan_prices_plan_market_interval_unique', $migration);
        $this->assertStringContainsString('plan_prices_market_currency_idx', $migration);
    }
}
