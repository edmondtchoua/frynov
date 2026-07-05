<?php

namespace App\Modules\Billing\Tests\Unit;

use App\Modules\Billing\Services\ProrationCalculator;
use App\Modules\Billing\Services\ProrationResult;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * RC-2A — calculateur de reliquat PUR. Matrice durcie par revue adverse :
 *  - arithmétique entière (déterministe) + bornes exactes (jour 1 = assiette, expiré = 0) ;
 *  - assiette = payé − trop-perçu (pas de double comptage RC-1C) ;
 *  - garde cross-devise (l'avoir ne franchit jamais une devise) ;
 *  - avoir reporté imputé même quand le crédit de temps est nul.
 */
class ProrationCalculatorTest extends TestCase
{
    private ProrationCalculator $calc;
    private CarbonImmutable $t0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calc = new ProrationCalculator();
        $this->t0   = CarbonImmutable::parse('2026-01-01 00:00:00');
    }

    /** Période synthétique de `$totalSec` secondes, observée à `$elapsedSec` écoulées. */
    private function compute(
        int $paid, int $totalSec, int $elapsedSec, int $newGross,
        string $status = 'active', string $cur = 'XOF', string $newCur = 'XOF',
        int $overpaid = 0, int $carried = 0,
    ): ProrationResult {
        $start = $this->t0;
        $end   = $this->t0->addSeconds($totalSec);
        $asOf  = $this->t0->addSeconds($elapsedSec);
        return $this->calc->compute($paid, $overpaid, $start, $end, $status, $cur, $newGross, $newCur, $asOf, $carried);
    }

    private function assertInvariants(ProrationResult $r, int $assiette): void
    {
        $this->assertGreaterThanOrEqual(0, $r->creditMinor);
        $this->assertLessThanOrEqual($assiette, $r->creditMinor);
        $this->assertGreaterThanOrEqual(0, $r->netPayableMinor);
        $this->assertSame($r->newGrossMinor, $r->appliedCreditMinor + $r->netPayableMinor);
        $this->assertGreaterThanOrEqual(0, $r->carryCreditMinor);
    }

    #[Test]
    public function not_paid_yields_no_credit(): void
    {
        $r = $this->compute(paid: 0, totalSec: 2592000, elapsedSec: 0, newGross: 990000);
        $this->assertFalse($r->eligible);
        $this->assertSame('not_paid', $r->reason);
        $this->assertSame(0, $r->creditMinor);
        $this->assertSame(990000, $r->netPayableMinor);
    }

    #[Test]
    public function past_due_without_period_yields_no_credit(): void
    {
        $r = $this->calc->compute(500000, 0, $this->t0, null, 'past_due', 'XOF', 990000, 'XOF', $this->t0->addDay());
        $this->assertSame('past_due_no_period', $r->reason);
        $this->assertSame(0, $r->creditMinor);
    }

    #[Test]
    public function suspended_subscription_is_not_eligible(): void
    {
        $r = $this->compute(paid: 990000, totalSec: 2592000, elapsedSec: 864000, newGross: 9900000, status: 'suspended');
        $this->assertSame('not_eligible', $r->reason);
        $this->assertSame(0, $r->creditMinor);
    }

    #[Test]
    public function expired_period_yields_no_credit(): void
    {
        $r = $this->compute(paid: 990000, totalSec: 2592000, elapsedSec: 3000000, newGross: 9900000);
        $this->assertSame('expired', $r->reason);
        $this->assertSame(0, $r->creditMinor);
    }

    #[Test]
    public function degenerate_period_yields_no_credit(): void
    {
        $r = $this->compute(paid: 990000, totalSec: 0, elapsedSec: 0, newGross: 9900000);
        $this->assertSame('degenerate_period', $r->reason);
    }

    #[Test]
    public function day_one_credits_the_full_assiette_exactly(): void
    {
        // now == start → fraction 1.0 exact, crédit = assiette pile (jamais assiette+1).
        $r = $this->compute(paid: 990000, totalSec: 2592000, elapsedSec: 0, newGross: 9900000);
        $this->assertTrue($r->eligible);
        $this->assertSame(990000, $r->creditMinor);
        $this->assertSame(990000, $r->appliedCreditMinor);
        $this->assertSame(9900000 - 990000, $r->netPayableMinor);
        $this->assertSame('ok', $r->reason);
        $this->assertInvariants($r, 990000);
    }

    #[Test]
    public function half_remaining_credits_half(): void
    {
        $r = $this->compute(paid: 990000, totalSec: 60, elapsedSec: 30, newGross: 9900000);
        $this->assertSame(495000, $r->creditMinor);
        $this->assertSame(9900000 - 495000, $r->netPayableMinor);
        $this->assertInvariants($r, 990000);
    }

    #[Test]
    public function tiny_remaining_floors_credit_to_zero(): void
    {
        // raw = 990000 * 1 / 2970000 ≈ 0.333 → round-half-up → 0.
        $r = $this->compute(paid: 990000, totalSec: 2970000, elapsedSec: 2969999, newGross: 9900000);
        $this->assertSame(0, $r->creditMinor);
        $this->assertSame(9900000, $r->netPayableMinor);
    }

    #[Test]
    public function credit_never_exceeds_assiette(): void
    {
        $r = $this->compute(paid: 990000, totalSec: 60, elapsedSec: 0, newGross: 9900000);
        $this->assertSame(990000, $r->creditMinor); // jamais 990001
    }

    #[Test]
    public function downgrade_carries_the_excess_as_voucher(): void
    {
        // Pro annuel payé 9.9M, jour 1 → crédit 9.9M ; downgrade vers Essentiel mensuel 990k.
        $r = $this->compute(paid: 9900000, totalSec: 2592000, elapsedSec: 0, newGross: 990000);
        $this->assertSame('downgrade', $r->reason);
        $this->assertSame(9900000, $r->creditMinor);
        $this->assertSame(990000, $r->appliedCreditMinor);
        $this->assertSame(0, $r->netPayableMinor);
        $this->assertSame(8910000, $r->carryCreditMinor); // avoir reporté, AUCUN cash
        $this->assertInvariants($r, 9900000);
    }

    #[Test]
    public function downgrade_to_free_plan_sends_all_to_carry(): void
    {
        $r = $this->compute(paid: 990000, totalSec: 2592000, elapsedSec: 0, newGross: 0);
        $this->assertSame('free_target', $r->reason);
        $this->assertSame(0, $r->appliedCreditMinor);
        $this->assertSame(0, $r->netPayableMinor);
        $this->assertSame(990000, $r->carryCreditMinor);
    }

    #[Test]
    public function xof_rounding_is_half_up_in_minor_units(): void
    {
        // raw = 100001 * 30 / 60 = 50000.5 → 50001.
        $r = $this->compute(paid: 100001, totalSec: 60, elapsedSec: 30, newGross: 9900000);
        $this->assertSame(0, $r->exponent);
        $this->assertSame(50001, $r->creditMinor);
    }

    #[Test]
    public function eur_rounding_keeps_minor_units_and_exponent_two(): void
    {
        // raw = 1999 * 1 / 3 ≈ 666.33 → 666.
        $r = $this->compute(paid: 1999, totalSec: 3, elapsedSec: 2, newGross: 490000, cur: 'EUR', newCur: 'EUR');
        $this->assertSame(2, $r->exponent);
        $this->assertSame(666, $r->creditMinor);
        $this->assertLessThanOrEqual(1999, $r->creditMinor);
    }

    #[Test]
    public function cross_currency_blocks_application_and_preserves_carried(): void
    {
        // Courant XOF (crédit + avoir reporté 600k) ; nouveau plan EUR → rien n'est imputé cross-devise.
        $r = $this->compute(paid: 990000, totalSec: 60, elapsedSec: 30, newGross: 1999, cur: 'XOF', newCur: 'EUR', carried: 600000);
        $this->assertSame('cross_currency_blocked', $r->reason);
        $this->assertFalse($r->eligible);
        $this->assertSame(0, $r->creditMinor);
        $this->assertSame(0, $r->appliedCreditMinor);
        $this->assertSame(1999, $r->netPayableMinor);     // tarif plein EUR
        $this->assertSame(600000, $r->carryCreditMinor);  // avoir XOF préservé, NON imputé
    }

    #[Test]
    public function carried_credit_is_reimputed_on_top_of_time_credit(): void
    {
        // crédit de temps 495000 + avoir reporté 600000 = 1 095 000 imputés.
        $r = $this->compute(paid: 990000, totalSec: 60, elapsedSec: 30, newGross: 9900000, carried: 600000);
        $this->assertSame(495000, $r->creditMinor);
        $this->assertSame(1095000, $r->appliedCreditMinor);
        $this->assertSame(9900000 - 1095000, $r->netPayableMinor);
        $this->assertSame(0, $r->carryCreditMinor);
    }

    #[Test]
    public function carried_credit_applies_even_when_time_credit_is_zero(): void
    {
        // Courant expiré (crédit de temps 0) MAIS avoir reporté 300k → s'impute quand même.
        $r = $this->compute(paid: 990000, totalSec: 2592000, elapsedSec: 3000000, newGross: 990000, carried: 300000);
        $this->assertSame('expired', $r->reason);
        $this->assertSame(0, $r->creditMinor);
        $this->assertSame(300000, $r->appliedCreditMinor);
        $this->assertSame(690000, $r->netPayableMinor);
        $this->assertSame(0, $r->carryCreditMinor);
    }

    #[Test]
    public function assiette_is_net_of_already_tracked_overpayment(): void
    {
        // Payé 10M dont 100k déjà tracés en trop-perçu (RC-1C) → assiette 9.9M, pas 10M.
        $r = $this->compute(paid: 10000000, totalSec: 2592000, elapsedSec: 0, newGross: 9900000, overpaid: 100000);
        $this->assertSame(9900000, $r->creditMinor); // sur l'assiette nette, pas 10M
        $this->assertInvariants($r, 9900000);
    }
}
