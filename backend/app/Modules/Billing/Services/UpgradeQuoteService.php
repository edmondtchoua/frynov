<?php

namespace App\Modules\Billing\Services;

use App\Modules\Billing\Exceptions\InvalidPromoCodeException;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Promotion;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Support\Markets;
use App\Modules\Tenants\Models\Tenant;

/**
 * Calcule un devis AUTORITATIF de changement de plan. Service PUR côté lecture (aucune écriture).
 *
 * P0 — le montant à payer est dérivé des prix serveur, jamais saisi par le client.
 * P0.1 — durée multi-période (mensuel 1–12, annuel 1–{@see self::MAX_YEARS}), total = tarif unitaire ×
 * durée ; promotion « en cours » appliquée AUTOMATIQUEMENT aux périodes COUVERTES par sa fenêtre de
 * validité (un code saisi reste prioritaire) ; proration réservée à la quantité 1 (le prépaiement en
 * gros ne se combine pas avec le reliquat mi-cycle).
 */
class UpgradeQuoteService
{
    /** Bornes de durée. */
    public const MAX_MONTHS = 12;
    public const MAX_YEARS  = 5;

    public function __construct(
        private readonly SubscriptionService $subscriptions,
        private readonly PromotionService    $promotions,
    ) {}

    /**
     * @param int     $quantity   nb de périodes payées d'avance (borné selon l'intervalle).
     * @param ?string $promoCode  code promo optionnel — validé ; invalide → ligne `valid=false`, non
     *                            appliquée, et l'auto-promo est ignorée (l'intention du client prime).
     * @param ?string $marketHint marché suggéré par l'UI (repli si pas de marché établi).
     */
    public function quote(
        Tenant $tenant,
        Plan $plan,
        string $interval,
        int $quantity = 1,
        ?string $promoCode = null,
        ?string $marketHint = null,
    ): UpgradeQuote {
        $interval = in_array($interval, [Subscription::INTERVAL_MONTHLY, Subscription::INTERVAL_YEARLY], true)
            ? $interval
            : Subscription::INTERVAL_MONTHLY;
        $quantity = $this->clampQuantity($interval, $quantity);

        $current = $this->subscriptions->current($tenant);
        $market  = $this->resolveMarket($current, $marketHint);

        $price     = $plan->priceForMarket($market, $interval);
        $unitGross = (int) ($price?->base_amount_minor ?? 0);
        $currency  = $price?->currency ?? ($current?->currency ?? Markets::currencyFor($market) ?? 'USD');
        $exponent  = Markets::exponentForCurrency($currency);
        $isFree    = $unitGross === 0;
        $subtotal  = $unitGross * $quantity;

        // ── Promo : code explicite prioritaire ; sinon promotion « en cours » auto ────────────────────
        [$promoLine, $discount] = $this->resolvePromo($tenant, $plan, $interval, $quantity, $unitGross, $subtotal, $promoCode);
        $grossAfterPromo = max(0, $subtotal - $discount);

        // ── Taxe (sur le brut après promo) + frais d'installation (UNIQUE, sur un vrai changement de
        //    plan — pas un renouvellement du même plan). ────────────────────────────────────────────
        $taxRateBps = (int) ($plan->tax_rate_bps ?? 0);
        $taxMinor   = (int) round($grossAfterPromo * $taxRateBps / 10000);
        $isPlanSwitch = $current === null || $current->plan_id !== $plan->id;
        $setupFee   = $isPlanSwitch ? (int) ($plan->setup_fee_minor ?? 0) : 0;

        // ── Proration : uniquement pour une période unique (quantité 1) ───────────────────────────────
        $chargeable    = $grossAfterPromo + $taxMinor + $setupFee;
        $appliedCredit = 0;
        $carryCredit   = 0;
        $fraction      = 0.0;
        if ($quantity === 1) {
            $proration     = $this->subscriptions->previewProration($tenant, $plan, $interval);
            $appliedCredit = min($proration->appliedCreditMinor, $chargeable);
            $carryCredit   = $proration->carryCreditMinor;
            $fraction      = $proration->fraction;
        }

        $netPayable = max(0, $chargeable - $appliedCredit);

        return new UpgradeQuote(
            planCode:              $plan->code,
            planName:              $plan->name,
            interval:              $interval,
            quantity:              $quantity,
            market:                $market,
            currency:              $currency,
            exponent:              $exponent,
            isFree:                $isFree,
            unitGrossMinor:        $unitGross,
            baseGrossMinor:        $unitGross,
            subtotalMinor:         $subtotal,
            promo:                 $promoLine,
            grossAfterPromoMinor:  $grossAfterPromo,
            taxRateBps:            $taxRateBps,
            taxMinor:              $taxMinor,
            setupFeeMinor:         $setupFee,
            appliedCreditMinor:    $appliedCredit,
            carryCreditMinor:      $carryCredit,
            prorationFraction:     $fraction,
            netPayableMinor:       $netPayable,
        );
    }

    private function clampQuantity(string $interval, int $quantity): int
    {
        $max = $interval === Subscription::INTERVAL_YEARLY ? self::MAX_YEARS : self::MAX_MONTHS;

        return max(1, min($max, $quantity));
    }

    /**
     * @return array{0:array<string,mixed>|null,1:int} [ligne promo|null, remise totale]
     */
    private function resolvePromo(
        Tenant $tenant,
        Plan $plan,
        string $interval,
        int $quantity,
        int $unitGross,
        int $subtotal,
        ?string $promoCode,
    ): array {
        $promoCode = $promoCode !== null ? trim($promoCode) : null;

        // ── Code explicite ────────────────────────────────────────────────────────────────────────
        if ($promoCode !== null && $promoCode !== '') {
            try {
                $promo = $this->promotions->validate($promoCode, $tenant, $plan->code);
            } catch (InvalidPromoCodeException $e) {
                return [[
                    'code'           => strtoupper($promoCode),
                    'valid'          => false,
                    'source'         => 'code',
                    'discount_minor' => 0,
                    'message'        => $e->getMessage(),
                ], 0];
            }

            return [$this->promoLine($promo, 'code', $interval, $quantity, $unitGross, $subtotal), $this->coveredDiscount($promo, $interval, $quantity, $unitGross, $subtotal)['discount']];
        }

        // ── Promotion « en cours » appliquée automatiquement (sans code) ────────────────────────────
        $promo = $this->promotions->activeFor($tenant, $plan->code, $unitGross);
        if ($promo === null) {
            return [null, 0];
        }

        $cov = $this->coveredDiscount($promo, $interval, $quantity, $unitGross, $subtotal);
        if ($cov['discount'] <= 0) {
            return [null, 0]; // promo active mais aucune période couverte → rien à afficher
        }

        return [$this->promoLine($promo, 'auto', $interval, $quantity, $unitGross, $subtotal), $cov['discount']];
    }

    /** @return array<string,mixed> */
    private function promoLine(Promotion $promo, string $source, string $interval, int $quantity, int $unitGross, int $subtotal): array
    {
        $cov = $this->coveredDiscount($promo, $interval, $quantity, $unitGross, $subtotal);

        return [
            'code'            => $promo->code,
            'valid'           => true,
            'source'          => $source,
            'discount_type'   => $promo->discount_type,
            'discount_value'  => $promo->discount_value,
            'covered_periods' => $cov['covered'],
            'discount_minor'  => $cov['discount'],
        ];
    }

    /**
     * Remise réellement due, limitée aux périodes COUVERTES par la fenêtre de validité de la promo.
     * Pourcentage → par période couverte ; montant fixe → remise unique (si ≥ 1 période couverte).
     *
     * @return array{covered:int,discount:int}
     */
    private function coveredDiscount(Promotion $promo, string $interval, int $quantity, int $unitGross, int $subtotal): array
    {
        $start   = now();
        $covered = 0;
        for ($i = 0; $i < $quantity; $i++) {
            $periodStart = $interval === Subscription::INTERVAL_YEARLY
                ? (clone $start)->addYears($i)
                : (clone $start)->addMonthsNoOverflow($i);

            $afterFrom = $promo->valid_from === null  || ! $periodStart->lessThan($promo->valid_from);
            $beforeEnd = $promo->valid_until === null || ! $periodStart->greaterThan($promo->valid_until);
            if ($afterFrom && $beforeEnd) {
                $covered++;
            }
        }

        if ($covered === 0) {
            return ['covered' => 0, 'discount' => 0];
        }

        if ($promo->discount_type === 'percent') {
            $perPeriod = (int) round($unitGross * ($promo->discount_value / 100));

            return ['covered' => $covered, 'discount' => min($subtotal, $perPeriod * $covered)];
        }

        // Montant fixe : remise unique (non multipliée par la durée).
        return ['covered' => 1, 'discount' => min($subtotal, (int) $promo->discount_value)];
    }

    /**
     * Résolution serveur-side du marché : abonnement courant (marché puis devise) en priorité, sinon
     * hint UI validé, sinon `global`.
     */
    private function resolveMarket(?Subscription $current, ?string $hint): string
    {
        if ($current?->market_code) {
            return $current->market_code;
        }

        if ($current?->currency) {
            $canonical = Markets::canonicalForCurrency($current->currency);
            if ($canonical !== null) {
                return $canonical;
            }
        }

        if ($hint !== null && $hint !== '') {
            $h = strtolower(trim($hint));
            if (Markets::isValid($h)) {
                return $h;
            }
        }

        return 'global';
    }
}
