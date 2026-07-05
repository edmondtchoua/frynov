<?php

namespace App\Modules\Billing\Tests\Unit;

use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Services\PaymentPeriodResolver;
use Database\Seeders\PlansSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * RC-1C — résolveur PUR de périodicité. Matrice durcie par revue adverse :
 *  - la bande ±1 % est une tolérance (pas un trop-perçu) ;
 *  - le trop-perçu n'existe qu'au-delà de la plus grande cible ;
 *  - un montant « zone morte » entre mensuel et annuel = acompte vers l'annuel ;
 *  - promo → needs_review ; devise inconnue → unmatched (jamais d'exception).
 *
 * Référence (PlansSeeder, plan Essentiel) : XOF mensuel 990000 / annuel 9 900 000 ; USD 1900 / 19000.
 */
class PaymentPeriodResolverTest extends TestCase
{
    use RefreshDatabase;

    private PaymentPeriodResolver $resolver;
    private Plan $essential;
    private Plan $free;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlansSeeder::class);
        $this->resolver  = new PaymentPeriodResolver();
        $this->essential = Plan::where('code', Plan::CODE_ESSENTIAL)->firstOrFail();
        $this->free      = Plan::where('code', Plan::CODE_STARTER)->firstOrFail();
    }

    #[Test]
    public function full_monthly_xof_is_matched_and_active(): void
    {
        $r = $this->resolver->resolve($this->essential, 990000, 'XOF');

        $this->assertSame('monthly', $r->interval);
        $this->assertSame('matched', $r->resolutionStatus);
        $this->assertTrue($r->isComplete);
        $this->assertFalse($r->isPartial);
        $this->assertSame(0, $r->remainingDueMinor);
        $this->assertSame(0, $r->overpaidMinor);
        $this->assertSame('waemu', $r->marketCode);
        $this->assertSame('currency', $r->marketSource);
    }

    #[Test]
    public function full_yearly_xof_is_matched_with_yearly_priority(): void
    {
        $r = $this->resolver->resolve($this->essential, 9900000, 'XOF');

        $this->assertSame('yearly', $r->interval);
        $this->assertTrue($r->isComplete);
        $this->assertSame(9900000, $r->targetMinor);
        $this->assertSame(0, $r->overpaidMinor);
    }

    #[Test]
    public function plus_one_percent_upper_bound_is_matched_without_false_overpaid(): void
    {
        // monthly=990000, tolérance=ceil(9900)=9900 → upper=999900. Bruit toléré, PAS un trop-perçu.
        $r = $this->resolver->resolve($this->essential, 999900, 'XOF');

        $this->assertSame('monthly', $r->interval);
        $this->assertSame('matched', $r->resolutionStatus);
        $this->assertSame(0, $r->overpaidMinor);
    }

    #[Test]
    public function minus_one_percent_lower_bound_is_matched(): void
    {
        $r = $this->resolver->resolve($this->essential, 980100, 'XOF'); // lower = 990000-9900

        $this->assertSame('monthly', $r->interval);
        $this->assertTrue($r->isComplete);
        $this->assertSame(0, $r->remainingDueMinor);
    }

    #[Test]
    public function just_below_lower_band_is_partial(): void
    {
        $r = $this->resolver->resolve($this->essential, 980099, 'XOF'); // 1 sous la borne basse

        $this->assertTrue($r->isPartial);
        $this->assertSame('partial', $r->resolutionStatus);
        $this->assertSame('monthly', $r->interval);
        $this->assertSame(990000 - 980099, $r->remainingDueMinor);
    }

    #[Test]
    public function first_deposit_is_partial_past_due(): void
    {
        $r = $this->resolver->resolve($this->essential, 495000, 'XOF', null, 'monthly', 0);

        $this->assertTrue($r->isPartial);
        $this->assertSame(990000, $r->targetMinor);
        $this->assertSame(495000, $r->remainingDueMinor);
        $this->assertSame(0, $r->overpaidMinor);
    }

    #[Test]
    public function cumulative_deposits_settle_to_active(): void
    {
        $r = $this->resolver->resolve($this->essential, 495000, 'XOF', null, 'monthly', 495000);

        $this->assertTrue($r->isComplete);
        $this->assertSame('matched', $r->resolutionStatus);
        $this->assertSame(990000, $r->paidCumulativeMinor);
        $this->assertSame(0, $r->remainingDueMinor);
    }

    #[Test]
    public function overpaid_only_beyond_the_largest_target(): void
    {
        // 10 000 000 > upper(annuel 9 900 000)=9 999 000 → trop-perçu réel = 100 000.
        $r = $this->resolver->resolve($this->essential, 10000000, 'XOF', null, 'monthly', 0);

        $this->assertTrue($r->isComplete);
        $this->assertSame('overpaid', $r->resolutionStatus);
        $this->assertSame('yearly', $r->interval);
        $this->assertSame(100000, $r->overpaidMinor);
        $this->assertSame(0, $r->remainingDueMinor);
    }

    #[Test]
    public function dead_zone_between_bands_routes_to_yearly_partial_not_overpaid(): void
    {
        // 1 100 000 : au-dessus de la bande mensuelle, sous l'annuelle, déclaré mensuel.
        // Correctif red-team : c'est un ACOMPTE vers l'annuel, pas un trop-perçu mensuel.
        $r = $this->resolver->resolve($this->essential, 1100000, 'XOF', null, 'monthly', 0);

        $this->assertTrue($r->isPartial);
        $this->assertSame('yearly', $r->interval);
        $this->assertSame(9900000 - 1100000, $r->remainingDueMinor);
        $this->assertSame(0, $r->overpaidMinor);
    }

    #[Test]
    public function free_plan_activates_monthly_with_any_amount_as_credit(): void
    {
        $r = $this->resolver->resolve($this->free, 5000, 'XOF');

        $this->assertSame('free', $r->resolutionStatus);
        $this->assertSame('monthly', $r->interval);
        $this->assertTrue($r->isComplete);
        $this->assertSame(0, $r->targetMinor);
        $this->assertSame(5000, $r->overpaidMinor);
    }

    #[Test]
    public function free_plan_zero_amount_activates_immediately(): void
    {
        $r = $this->resolver->resolve($this->free, 0, 'XOF');

        $this->assertTrue($r->isComplete);
        $this->assertSame('free', $r->resolutionStatus);
        $this->assertSame(0, $r->overpaidMinor);
    }

    #[Test]
    public function unknown_currency_is_unmatched_without_exception(): void
    {
        $r = $this->resolver->resolve($this->essential, 990000, 'JPY');

        $this->assertSame('unmatched', $r->resolutionStatus);
        $this->assertNull($r->interval);
        $this->assertFalse($r->isComplete);
        $this->assertFalse($r->isPartial);
    }

    #[Test]
    public function usd_defaults_to_usa_market(): void
    {
        $r = $this->resolver->resolve($this->essential, 1900, 'USD');

        $this->assertSame('usa', $r->marketCode);
        $this->assertSame('currency', $r->marketSource);
        $this->assertSame('monthly', $r->interval);
        $this->assertTrue($r->isComplete);
    }

    #[Test]
    public function coherent_usd_hint_global_is_honored(): void
    {
        $r = $this->resolver->resolve($this->essential, 1900, 'USD', 'global');

        $this->assertSame('global', $r->marketCode);
        $this->assertSame('hint', $r->marketSource);
        $this->assertTrue($r->isComplete);
    }

    #[Test]
    public function incoherent_hint_is_ignored_and_falls_back_to_currency(): void
    {
        // hint 'europe' = EUR, incohérent avec un paiement XOF → ignoré.
        $r = $this->resolver->resolve($this->essential, 990000, 'XOF', 'europe');

        $this->assertSame('waemu', $r->marketCode);
        $this->assertSame('currency', $r->marketSource);
    }

    #[Test]
    public function declared_yearly_partial_routes_to_yearly_target(): void
    {
        $r = $this->resolver->resolve($this->essential, 1000000, 'XOF', null, 'yearly');

        $this->assertTrue($r->isPartial);
        $this->assertSame('yearly', $r->interval);
        $this->assertSame(9900000, $r->targetMinor);
        $this->assertSame(9900000 - 1000000, $r->remainingDueMinor);
    }

    #[Test]
    public function tiny_deposit_without_declaration_targets_the_smallest_target(): void
    {
        $r = $this->resolver->resolve($this->essential, 100000, 'XOF');

        $this->assertTrue($r->isPartial);
        $this->assertSame('monthly', $r->interval);
        $this->assertSame(890000, $r->remainingDueMinor);
    }

    #[Test]
    public function a_promo_routes_to_needs_review(): void
    {
        $r = $this->resolver->resolve($this->essential, 990000, 'XOF', null, 'monthly', 0, true);

        $this->assertSame('needs_review', $r->resolutionStatus);
        $this->assertNull($r->interval);
        $this->assertFalse($r->isComplete);
        $this->assertFalse($r->isPartial);
    }
}
