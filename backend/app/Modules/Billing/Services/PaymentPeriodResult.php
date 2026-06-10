<?php

namespace App\Modules\Billing\Services;

/**
 * Résultat (immuable, sérialisable pour l'audit) d'une résolution de périodicité de paiement.
 *
 * `resolutionStatus` :
 *  - `matched`      : le cumul solde une cible (mensuel/annuel) dans la bande ±1% → abonnement actif ;
 *  - `partial`      : acompte < cible → abonnement `past_due` (reste dû tracé) ;
 *  - `overpaid`     : cumul au-delà de la plus grande cible → soldé + avoir (trop-perçu) ;
 *  - `free`         : plan gratuit → actif immédiatement, tout versement = avoir ;
 *  - `needs_review` : promo/cas ambigu → l'admin tranche, pas d'activation automatique ;
 *  - `unmatched`    : devise hors référentiel → approuvé sans activation.
 */
final class PaymentPeriodResult
{
    public function __construct(
        public readonly string  $marketCode,
        public readonly string  $marketSource,        // hint|currency|fallback|unmatched
        public readonly ?string $interval,            // monthly|yearly|null
        public readonly ?int    $targetMinor,         // cible nette (null si gratuit/unmatched/needs_review)
        public readonly int     $paidCumulativeMinor, // cumul efficace (acomptes non soldés + versement courant)
        public readonly bool    $isComplete,          // solde la cible → active
        public readonly bool    $isPartial,           // acompte < cible → past_due
        public readonly int     $remainingDueMinor,   // >= 0
        public readonly int     $overpaidMinor,       // >= 0 (avoir)
        public readonly string  $resolutionStatus,
    ) {}
}
