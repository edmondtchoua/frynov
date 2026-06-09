<?php

namespace App\Modules\Billing\Tests\Integration;

use App\Modules\Billing\Models\MarketPaymentMethod;
use Database\Seeders\MarketPaymentMethodsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * P6-1 — GET /api/public/payment-methods : moyens de paiement par marché.
 * Endpoint PUBLIC (pas d'auth). À ce stade tout est manual/quote (aucun rail PSP réel) ;
 * le DoD est satisfait si CHAQUE devise renvoie ≥1 moyen (flux manuel OU mention sur devis).
 */
class PublicPaymentMethodsTest extends TestCase
{
    use RefreshDatabase;

    /** Les 10 marchés (code => devise), miroir de PublicPricingController::MARKETS. */
    private const MARKETS = [
        'waemu' => 'XOF', 'cemac' => 'XAF', 'nigeria' => 'NGN', 'ghana' => 'GHS',
        'kenya' => 'KES', 'south_africa' => 'ZAR', 'europe' => 'EUR', 'canada' => 'CAD',
        'usa' => 'USD', 'global' => 'USD',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MarketPaymentMethodsSeeder::class);
    }

    #[Test]
    public function every_market_returns_at_least_one_method_in_its_currency(): void
    {
        foreach (self::MARKETS as $code => $currency) {
            $res = $this->getJson("/api/public/payment-methods?market={$code}")->assertOk();

            $this->assertSame($code, $res->json('market.code'));
            $this->assertSame($currency, $res->json('market.currency'));
            $this->assertNotEmpty($res->json('data'), "le marché {$code} doit exposer ≥1 moyen (DoD)");
            // Chaque moyen porte la devise du marché.
            foreach ($res->json('data') as $m) {
                $this->assertSame($currency, $m['currency']);
            }
        }
    }

    #[Test]
    public function waemu_exposes_wave_and_orange_money(): void
    {
        $methods = collect($this->getJson('/api/public/payment-methods?market=waemu')->assertOk()->json('data'))
            ->pluck('method')->all();

        $this->assertContains('wave', $methods);
        $this->assertContains('orange_money', $methods);
    }

    #[Test]
    public function it_resolves_the_market_from_a_country(): void
    {
        $res = $this->getJson('/api/public/payment-methods?country=SN')->assertOk();

        $this->assertSame('waemu', $res->json('market.code'));
        $this->assertSame('country', $res->json('market.source'));
    }

    #[Test]
    public function an_unknown_market_falls_back_to_global_usd(): void
    {
        $res = $this->getJson('/api/public/payment-methods?market=atlantis')->assertOk();

        $this->assertSame('global', $res->json('market.code'));
        $this->assertSame('fallback', $res->json('market.source'));
        $this->assertSame('USD', $res->json('market.currency'));
    }

    #[Test]
    public function at_this_stage_no_method_is_an_auto_rail(): void
    {
        // NO-GO : aucun rail PSP réel branché. Tout doit être manual ou quote.
        $allModes = MarketPaymentMethod::query()->pluck('mode')->unique()->values()->all();

        sort($allModes);
        $this->assertSame(['manual', 'quote'], $allModes);

        $res = $this->getJson('/api/public/payment-methods?market=europe')->assertOk();
        $this->assertFalse($res->json('has_auto'));
        foreach ($res->json('data') as $m) {
            $this->assertContains($m['mode'], ['manual', 'quote']);
        }
    }
}
