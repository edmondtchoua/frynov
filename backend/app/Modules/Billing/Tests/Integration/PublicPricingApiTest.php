<?php

namespace App\Modules\Billing\Tests\Integration;

use App\Modules\Billing\Models\Plan;
use Database\Seeders\PlansSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PublicPricingApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function guest_can_get_canadian_pricing_from_country_without_authentication(): void
    {
        $this->seed(PlansSeeder::class);

        $response = $this->getJson('/api/public/pricing?country=CA');

        $response->assertOk()
            ->assertJsonPath('market.code', 'canada')
            ->assertJsonPath('market.currency', 'CAD')
            ->assertJsonPath('market.source', 'country')
            ->assertJsonPath('data.1.code', Plan::CODE_ESSENTIAL)
            ->assertJsonPath('data.1.price.currency', 'CAD')
            ->assertJsonPath('data.1.price.base_amount_minor', 2500)
            ->assertJsonPath('data.1.price.included_users', 2)
            ->assertJsonPath('data.1.limits.max_products', 500);
    }

    #[Test]
    public function explicit_market_selection_overrides_country_detection(): void
    {
        $this->seed(PlansSeeder::class);

        $response = $this->getJson('/api/public/pricing?country=CA&market=waemu');

        $response->assertOk()
            ->assertJsonPath('market.code', 'waemu')
            ->assertJsonPath('market.currency', 'XOF')
            ->assertJsonPath('market.source', 'market')
            ->assertJsonPath('data.1.price.currency', 'XOF')
            ->assertJsonPath('data.1.price.base_amount_minor', 990000);
    }

    #[Test]
    public function unknown_country_falls_back_to_global_usd_pricing(): void
    {
        $this->seed(PlansSeeder::class);

        $response = $this->getJson('/api/public/pricing?country=XX');

        $response->assertOk()
            ->assertJsonPath('market.code', 'global')
            ->assertJsonPath('market.currency', 'USD')
            ->assertJsonPath('market.source', 'fallback')
            ->assertJsonPath('data.1.price.currency', 'USD')
            ->assertJsonPath('data.1.price.base_amount_minor', 1900);
    }

    #[Test]
    public function unsupported_interval_falls_back_to_monthly_prices(): void
    {
        $this->seed(PlansSeeder::class);

        $response = $this->getJson('/api/public/pricing?market=canada&interval=yearly');

        $response->assertOk()
            ->assertJsonPath('data.1.price.interval', 'monthly')
            ->assertJsonPath('data.1.price.currency', 'CAD')
            ->assertJsonPath('data.1.price.base_amount_minor', 2500);
    }

    #[Test]
    public function inactive_or_private_plans_are_not_exposed_publicly(): void
    {
        $this->seed(PlansSeeder::class);

        Plan::where('code', Plan::CODE_ENTERPRISE)->update(['is_public' => false]);
        Plan::where('code', Plan::CODE_PRO)->update(['is_active' => false]);

        $response = $this->getJson('/api/public/pricing?market=europe');

        $response->assertOk();
        $this->assertSame(
            [Plan::CODE_STARTER, Plan::CODE_ESSENTIAL],
            collect($response->json('data'))->pluck('code')->all(),
        );
    }
}
