<?php

namespace App\Modules\Billing\Services;

/**
 * Devis (immuable, sérialisable) d'un changement de plan, calculé INTÉGRALEMENT côté serveur.
 *
 * P0 — « montant autoritatif ». Le montant à payer n'est plus une saisie libre du client : il est
 * dérivé des prix serveur (`plan_prices`) pour le marché/devise résolu serveur-side, moins une promo
 * VALIDÉE, moins l'avoir de proration (temps non consommé + avoir reporté). Le front verrouille le
 * champ sur `net_payable_minor` et affiche le détail ci-dessous ; il ne peut plus falsifier le total
 * (critère d'acceptation #22).
 *
 * Convention de montant (UNIFORME, tout le billing) : les `*_minor` valent le prix réel × 100 pour
 * TOUTES les devises (990000 = 9 900 XOF ; 2500 = 25,00 CAD). L'affichage divise donc toujours par
 * 100 ; `exponent` (0 pour XOF/XAF, sinon 2) ne pilote QUE le nombre de décimales à afficher.
 * Convention d'ordre : base → remise promo → avoir de proration → net.
 */
final class UpgradeQuote
{
    /**
     * @param array{code:string,valid:bool,source?:string,discount_type?:string,discount_value?:int,covered_periods?:int,discount_minor:int,message?:string}|null $promo
     */
    public function __construct(
        public readonly string $planCode,
        public readonly string $planName,
        public readonly string $interval,          // monthly|yearly
        public readonly int    $quantity,          // nb de périodes payées d'avance (P0.1)
        public readonly string $market,            // waemu|cemac|…|global
        public readonly string $currency,          // ISO 4217
        public readonly int    $exponent,          // décimales d'affichage de la devise
        public readonly bool   $isFree,            // plan gratuit (base = 0)
        public readonly int    $unitGrossMinor,    // tarif plein d'UNE période (plan/interval/marché)
        public readonly int    $baseGrossMinor,    // = unitGross (compat P0)
        public readonly int    $subtotalMinor,     // unitGross × quantity
        public readonly ?array $promo,             // ligne promo (null si aucune promo applicable)
        public readonly int    $grossAfterPromoMinor,
        public readonly int    $taxRateBps,        // taux de taxe en points de base (1800 = 18 %)
        public readonly int    $taxMinor,          // taxe sur le brut après promo
        public readonly int    $setupFeeMinor,     // frais d'installation UNIQUE (changement de plan)
        public readonly int    $appliedCreditMinor, // avoir de proration imputé (quantité 1 uniquement)
        public readonly int    $carryCreditMinor,   // avoir résiduel reporté (downgrade / crédit > tarif)
        public readonly float  $prorationFraction,  // 0..1 — temps non consommé (affichage)
        public readonly int    $netPayableMinor,    // ← montant AUTORITATIF à encaisser (≥ 0)
    ) {}

    /** @return array<string,mixed> Payload public du devis. */
    public function toArray(): array
    {
        return [
            'plan_code'              => $this->planCode,
            'plan_name'              => $this->planName,
            'interval'               => $this->interval,
            'quantity'               => $this->quantity,
            'market'                 => $this->market,
            'currency'               => $this->currency,
            'exponent'               => $this->exponent,
            'is_free'                => $this->isFree,
            'unit_gross_minor'       => $this->unitGrossMinor,
            'base_gross_minor'       => $this->baseGrossMinor,
            'subtotal_minor'         => $this->subtotalMinor,
            'promo'                  => $this->promo,
            'gross_after_promo_minor' => $this->grossAfterPromoMinor,
            'tax_rate_bps'           => $this->taxRateBps,
            'tax_minor'              => $this->taxMinor,
            'setup_fee_minor'        => $this->setupFeeMinor,
            'proration'              => [
                'applied_credit_minor' => $this->appliedCreditMinor,
                'carry_credit_minor'   => $this->carryCreditMinor,
                'fraction_remaining'   => $this->prorationFraction,
            ],
            'net_payable_minor'      => $this->netPayableMinor,
        ];
    }
}
