<?php

namespace App\Modules\Billing\Tests\Integration;

use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\PlanPrice;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Tenants\Models\Tenant;
use Database\Seeders\PlansSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * RC-0 (socle Billing périodicité) : prix annuels seedés (= mensuel ×10) pour tous les
 * plans×marchés, et l'abonnement porte une colonne `interval` (défaut mensuel).
 */
class PlanYearlyPricingTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function every_monthly_price_has_a_yearly_counterpart_at_ten_times(): void
    {
        $this->seed(PlansSeeder::class);

        $monthlies = PlanPrice::where('interval', 'monthly')->get();
        $this->assertNotEmpty($monthlies, 'le seeder doit créer des prix mensuels');

        foreach ($monthlies as $m) {
            $yearly = PlanPrice::where('plan_id', $m->plan_id)
                ->where('market_code', $m->market_code)
                ->where('interval', 'yearly')
                ->first();

            $this->assertNotNull($yearly, "prix annuel manquant pour {$m->plan_id}/{$m->market_code}");
            $this->assertSame($m->base_amount_minor * 10, $yearly->base_amount_minor);
            $this->assertSame($m->currency, $yearly->currency);
        }

        $this->assertSame(
            PlanPrice::where('interval', 'monthly')->count(),
            PlanPrice::where('interval', 'yearly')->count(),
        );
    }

    #[Test]
    public function a_subscription_carries_an_interval_defaulting_to_monthly(): void
    {
        $this->seed(PlansSeeder::class);
        $tenant = Tenant::create(['name' => 'T', 'slug' => 'yp-shop', 'plan' => 'starter', 'status' => 'active', 'settings' => []]);
        $plan   = Plan::query()->firstOrFail();

        $sub = Subscription::create([
            'tenant_id' => $tenant->id,
            'plan_id'   => $plan->id,
            'status'    => Subscription::STATUS_ACTIVE,
        ]);

        $reloaded = Subscription::withoutTenantScope()->findOrFail($sub->id);
        $this->assertSame('monthly', $reloaded->interval); // défaut DB

        $reloaded->update([
            'interval'          => Subscription::INTERVAL_YEARLY,
            'currency'          => 'XOF',
            'market_code'       => 'waemu',
            'amount_paid_minor' => 9_900_000,
        ]);

        $again = Subscription::withoutTenantScope()->findOrFail($sub->id);
        $this->assertSame('yearly', $again->interval);
        $this->assertSame(9_900_000, $again->amount_paid_minor);
    }
}
