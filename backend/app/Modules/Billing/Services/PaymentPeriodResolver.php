<?php

namespace App\Modules\Billing\Services;

use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Support\Markets;

/**
 * Détecte la périodicité (mensuel/annuel) d'un paiement manuel à partir du MONTANT encaissé, comparé
 * aux prix du plan pour le marché résolu, avec une tolérance ±1 % (bruit mobile money / FX). Gère
 * l'acompte échelonné : le cumul des acomptes non soldés détermine si la cible est atteinte.
 *
 * Classe PURE : ne lit que les prix du plan (PlanPrice), n'écrit rien. Tous les montants sont en
 * unités MINEURES (XOF/XAF : unité = unité mineure).
 *
 * Conception durcie par revue adverse (workflow RC-1C) :
 *  - la bande ±1 % est une TOLÉRANCE, pas un trop-perçu : un paiement plein dans la bande est `matched`
 *    (avoir 0), jamais `overpaid` ;
 *  - le trop-perçu n'existe QUE au-delà de la plus grande cible disponible ;
 *  - un montant entre le mensuel et l'annuel (« zone morte ») est un ACOMPTE vers l'annuel, pas un
 *    trop-perçu mensuel ;
 *  - le cumul ne somme QUE les acomptes non soldés (le renouvellement repart de zéro — géré côté service) ;
 *  - une promo rend la cible incertaine → `needs_review` (pas d'activation auto).
 */
final class PaymentPeriodResolver
{
    /** RC-6G — borne anti-faux-positif du matching multi-sièges. */
    private const MAX_EXTRA_USERS = 100;

    /**
     * @param int     $amountMinor       versement courant (>= 0, unités mineures)
     * @param string  $currency          ISO 4217 du paiement
     * @param ?string $marketHint        marché suggéré (moyen de paiement/UI), optionnel — ignoré s'il
     *                                    ne correspond pas à la devise
     * @param string  $declaredInterval  périodicité déclarée au submit (repli pour router un acompte)
     * @param int     $alreadyPaidMinor  cumul des acomptes NON SOLDÉS déjà approuvés sur la même cible
     * @param bool    $hasPromo          un code promo est appliqué → cible nette incertaine → needs_review
     * @param ?\Closure $netOfPromo      RC-6G (promo_net_target) — fn(int $base): int appliquant la remise
     *                                    d'une promo VALIDÉE : les cibles deviennent nettes et le paiement
     *                                    promo est résolu automatiquement (plus de needs_review)
     * @param bool    $matchExtraUsers   RC-6G (extra_user_matching) — tenter base + k×siège additionnel
     */
    public function resolve(
        Plan $plan,
        int $amountMinor,
        string $currency,
        ?string $marketHint = null,
        string $declaredInterval = 'monthly',
        int $alreadyPaidMinor = 0,
        bool $hasPromo = false,
        ?\Closure $netOfPromo = null,
        bool $matchExtraUsers = false,
    ): PaymentPeriodResult {
        $currency         = strtoupper(trim($currency));
        $declaredInterval = in_array($declaredInterval, ['monthly', 'yearly'], true) ? $declaredInterval : 'monthly';
        $cumul            = max(0, $alreadyPaidMinor) + max(0, $amountMinor);

        // ── Marché : devise inconnue → fail-safe « unmatched » (jamais d'exception) ──────────
        [$market, $source] = $this->resolveMarket($currency, $marketHint);
        if ($market === null) {
            return new PaymentPeriodResult('global', 'unmatched', null, null, $cumul, false, false, 0, 0, 'unmatched');
        }

        // ── Cibles : BASE (RC-6G : nettes de promo quand une promo VALIDÉE est fournie) ──────
        $monthlyPrice = $plan->priceForMarket($market, 'monthly');
        $yearlyPrice  = $plan->priceForMarket($market, 'yearly');
        $monthly = (int) ($monthlyPrice?->base_amount_minor ?? 0);
        $yearly  = (int) ($yearlyPrice?->base_amount_minor ?? 0);

        // ── Plan gratuit : activation immédiate, tout versement = avoir ───────────────────────
        if ($monthly === 0 && $yearly === 0) {
            return new PaymentPeriodResult($market, $source, 'monthly', 0, $cumul, true, false, 0, $cumul, 'free');
        }

        // ── Promo (RC-6G promo_net_target) : promo validée → cibles NETTES, résolution auto ;
        //    sinon (règle off ou promo invalide) : l'admin tranche, comme en RC-1C. ─────────────
        if ($hasPromo) {
            if ($netOfPromo === null) {
                return new PaymentPeriodResult($market, $source, null, null, $cumul, false, false, 0, 0, 'needs_review');
            }
            $monthly = $monthly > 0 ? max(0, (int) $netOfPromo($monthly)) : 0;
            $yearly  = $yearly > 0 ? max(0, (int) $netOfPromo($yearly)) : 0;
            if ($monthly === 0 && $yearly === 0) {
                // Remise à 100 % : rien à payer, tout versement = avoir.
                return new PaymentPeriodResult($market, $source, 'monthly', 0, $cumul, true, false, 0, $cumul, 'free');
            }
        }

        // ── Solde d'une cible (annuel d'abord ; les bandes ±1 % ne se recoupent jamais car
        //    annuel = 10× mensuel) ──────────────────────────────────────────────────────────
        if ($yearly > 0 && $this->matches($cumul, $yearly)) {
            return $this->settled($market, $source, 'yearly', $yearly, $cumul);
        }
        if ($monthly > 0 && $this->matches($cumul, $monthly)) {
            return $this->settled($market, $source, 'monthly', $monthly, $cumul);
        }

        // ── RC-6G (extra_user_matching) : base + k × siège additionnel (1 ≤ k ≤ borne), UNIQUEMENT
        //    sur l'intervalle DÉCLARÉ — sinon un trop-perçu annuel se ferait passer pour « N sièges
        //    mensuels » (les grilles se recouvrent dès que k grandit). La borne coupe le reste des
        //    faux positifs (avec ±1 %, un k démesuré finit toujours par matcher). ─────────────────
        if ($matchExtraUsers) {
            [$target, $price] = $declaredInterval === 'yearly' ? [$yearly, $yearlyPrice] : [$monthly, $monthlyPrice];
            $extra = (int) ($price?->extra_user_amount_minor ?? 0);
            if ($target > 0 && $extra > 0 && $cumul > $target) {
                $k = (int) round(($cumul - $target) / $extra);
                if ($k >= 1 && $k <= self::MAX_EXTRA_USERS && $this->matches($cumul, $target + $k * $extra)) {
                    return $this->settled($market, $source, $declaredInterval, $target + $k * $extra, $cumul, $k);
                }
            }
        }

        $largest         = $yearly > 0 ? $yearly : $monthly;
        $largestInterval = $yearly > 0 ? 'yearly' : 'monthly';

        // ── Trop-perçu : UNIQUEMENT au-delà de la borne haute de la plus grande cible ────────
        if ($cumul > $this->upper($largest)) {
            return new PaymentPeriodResult(
                $market, $source, $largestInterval, $largest, $cumul,
                true, false, 0, $cumul - $largest, 'overpaid',
            );
        }

        // ── Acompte (partial) : cible = la plus petite dont la borne haute couvre le cumul
        //    (un cumul en « zone morte » entre mensuel et annuel vise donc l'annuel). Une
        //    périodicité ANNUELLE déclarée force la cible annuelle. ───────────────────────────
        [$targetInterval, $target] = $this->partialTarget($declaredInterval, $monthly, $yearly, $cumul, $largestInterval, $largest);

        return new PaymentPeriodResult(
            $market, $source, $targetInterval, $target, $cumul,
            false, true, max(0, $target - $cumul), 0, 'partial',
        );
    }

    /** Cumul SOLDÉ dans la bande de tolérance : pas de trop-perçu (bruit absorbé). */
    private function settled(string $market, string $source, string $interval, int $target, int $cumul, int $extraUsers = 0): PaymentPeriodResult
    {
        return new PaymentPeriodResult($market, $source, $interval, $target, $cumul, true, false, 0, 0, 'matched', $extraUsers);
    }

    /**
     * @return array{0:string,1:int} [interval, cible] de l'acompte.
     */
    private function partialTarget(string $declaredInterval, int $monthly, int $yearly, int $cumul, string $largestInterval, int $largest): array
    {
        if ($declaredInterval === 'yearly' && $yearly > 0) {
            return ['yearly', $yearly];
        }

        foreach ([['monthly', $monthly], ['yearly', $yearly]] as [$iv, $t]) {
            if ($t > 0 && $cumul <= $this->upper($t)) {
                return [$iv, $t];
            }
        }

        return [$largestInterval, $largest];
    }

    /**
     * @return array{0:?string,1:string} [marketCode|null, source]
     */
    private function resolveMarket(string $currency, ?string $hint): array
    {
        if ($hint !== null && $hint !== '') {
            $h = strtolower(trim($hint));
            if (Markets::isValid($h) && Markets::currencyFor($h) === $currency) {
                return [$h, 'hint'];
            }
        }

        $canonical = Markets::canonicalForCurrency($currency);
        if ($canonical === null) {
            return [null, 'unmatched'];
        }

        return [$canonical, $canonical === 'global' ? 'fallback' : 'currency'];
    }

    // ── Tolérance ±1 % à bornes entières arrondies vers l'extérieur (>= 1 unité mineure) ─────

    private function tolerance(int $target): int
    {
        return max(1, (int) ceil($target * 0.01));
    }

    private function lower(int $target): int
    {
        return $target - $this->tolerance($target);
    }

    private function upper(int $target): int
    {
        return $target + $this->tolerance($target);
    }

    private function matches(int $amount, int $target): bool
    {
        return $amount >= $this->lower($target) && $amount <= $this->upper($target);
    }
}
