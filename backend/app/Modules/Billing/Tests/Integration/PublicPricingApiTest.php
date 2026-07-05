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
    public function each_target_country_resolves_its_local_market_and_currency(): void
    {
        $this->seed(PlansSeeder::class);

        // Localized-landing DoD: the visitor's country drives the currency —
        // never a default XOF for Canada/France.
        $cases = [
            ['SN', 'waemu', 'XOF'],
            ['CM', 'cemac', 'XAF'],
            ['FR', 'europe', 'EUR'],
            ['CA', 'canada', 'CAD'],
        ];

        foreach ($cases as [$country, $market, $currency]) {
            $this->getJson("/api/public/pricing?country={$country}")
                ->assertOk()
                ->assertJsonPath('market.code', $market)
                ->assertJsonPath('market.currency', $currency)
                ->assertJsonPath('market.source', 'country')
                ->assertJsonPath('data.1.price.currency', $currency);
        }
    }

    #[Test]
    public function yearly_interval_returns_annual_prices_with_savings_economics(): void
    {
        $this->seed(PlansSeeder::class);

        // Canada Essentiel : mensuel 2500 → annuel ×10 = 25000.
        // Équivalent mensuel = round(25000/12) = 2083 ; économie vs 12×2500=30000 :
        // 5000 (≈ 17 %).
        $response = $this->getJson('/api/public/pricing?market=canada&interval=yearly');

        $response->assertOk()
            ->assertJsonPath('interval', 'yearly')
            ->assertJsonPath('data.1.price.interval', 'yearly')
            ->assertJsonPath('data.1.price.currency', 'CAD')
            ->assertJsonPath('data.1.price.base_amount_minor', 25000)
            ->assertJsonPath('data.1.price.monthly_equivalent_minor', 2083)
            ->assertJsonPath('data.1.price.savings_amount_minor', 5000)
            ->assertJsonPath('data.1.price.savings_pct', 17);
    }

    #[Test]
    public function free_plan_yearly_stays_zero_with_no_savings(): void
    {
        $this->seed(PlansSeeder::class);

        // Le plan Découverte (gratuit) reste à 0 en annuel, sans économie fictive.
        $this->getJson('/api/public/pricing?market=canada&interval=yearly')
            ->assertOk()
            ->assertJsonPath('data.0.price.base_amount_minor', 0)
            ->assertJsonPath('data.0.price.monthly_equivalent_minor', 0)
            ->assertJsonPath('data.0.price.savings_amount_minor', 0)
            ->assertJsonPath('data.0.price.savings_pct', 0);
    }

    #[Test]
    public function truly_unsupported_interval_falls_back_to_monthly_prices(): void
    {
        $this->seed(PlansSeeder::class);

        // Une périodicité hors whitelist (ex : weekly) retombe sur le mensuel —
        // et n'expose AUCUN champ d'économie (réservé à l'annuel).
        $response = $this->getJson('/api/public/pricing?market=canada&interval=weekly');

        $response->assertOk()
            ->assertJsonPath('interval', 'monthly')
            ->assertJsonPath('data.1.price.interval', 'monthly')
            ->assertJsonPath('data.1.price.currency', 'CAD')
            ->assertJsonPath('data.1.price.base_amount_minor', 2500)
            ->assertJsonMissingPath('data.1.price.savings_pct');
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

    // ── /api/public/geo — privacy-first country detection (no third-party call) ──

    #[Test]
    public function geo_endpoint_returns_country_from_a_cdn_edge_header(): void
    {
        $this->getJson('/api/public/geo', ['CF-IPCountry' => 'SN'])
            ->assertOk()
            ->assertExactJson(['country_code' => 'SN']);
    }

    #[Test]
    public function geo_endpoint_returns_null_without_an_edge_header(): void
    {
        $this->getJson('/api/public/geo')
            ->assertOk()
            ->assertExactJson(['country_code' => null]);
    }

    #[Test]
    public function geo_endpoint_ignores_placeholder_country_codes(): void
    {
        $this->getJson('/api/public/geo', ['CF-IPCountry' => 'XX'])
            ->assertOk()
            ->assertExactJson(['country_code' => null]);
    }
}
