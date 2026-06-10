<?php

namespace App\Modules\Billing\Services;

/**
 * Résultat (immuable, sérialisable) d'un calcul de reliquat (proration) à l'upgrade/downgrade.
 *
 * Modèle d'application HYBRIDE (décision produit) :
 *  - le crédit du temps non consommé + l'avoir reporté sont **appliqués** au nouveau plan à hauteur de
 *    son tarif (`appliedCreditMinor`) → le client paie le **net** (`netPayableMinor`) ;
 *  - l'excédent éventuel (downgrade / crédit > tarif) part en **avoir reporté** (`carryCreditMinor`),
 *    jamais en cash.
 *
 * Invariants : `appliedCreditMinor + netPayableMinor == newGrossMinor` ; `creditMinor + carriedIn ==
 * appliedCreditMinor + carryCreditMinor` ; `0 <= creditMinor <= assiette` ; tous ≥ 0.
 */
final class ProrationResult
{
    public function __construct(
        public readonly bool   $eligible,           // un crédit de TEMPS payé a-t-il été généré ?
        public readonly float  $fraction,           // 0..1 — temps NON consommé (affichage)
        public readonly string $currency,
        public readonly int    $exponent,           // Markets::exponentForCurrency (affichage)
        public readonly int    $creditMinor,        // avoir du temps non consommé (0..assiette)
        public readonly int    $appliedCreditMinor, // imputé sur ce cycle = min(credit+carried, newGross)
        public readonly int    $carryCreditMinor,   // avoir résiduel reporté → metadata['credit_minor']
        public readonly int    $newGrossMinor,      // tarif plein du nouveau plan/interval/marché
        public readonly int    $netPayableMinor,    // newGross - applied (≥ 0) → à encaisser
        public readonly string $reason,             // ok|downgrade|free_target|not_paid|past_due_no_period|not_eligible|expired|degenerate_period|cross_currency_blocked
    ) {}

    /** @return array<string,mixed> Payload public (preview). */
    public function toArray(): array
    {
        return [
            'eligible'             => $this->eligible,
            'reason'               => $this->reason,
            'currency'             => $this->currency,
            'exponent'             => $this->exponent,
            'fraction_remaining'   => $this->fraction,
            'credit_minor'         => $this->creditMinor,
            'new_gross_minor'      => $this->newGrossMinor,
            'applied_credit_minor' => $this->appliedCreditMinor,
            'net_payable_minor'    => $this->netPayableMinor,
            'carry_credit_minor'   => $this->carryCreditMinor,
        ];
    }
}
