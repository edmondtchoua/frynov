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
    /**
     * @param int     $amountMinor       versement courant (>= 0, unités mineures)
     * @param string  $currency          ISO 4217 du paiement
     * @param ?string $marketHint        marché suggéré (moyen de paiement/UI), optionnel — ignoré s'il
     *                                    ne correspond pas à la devise
     * @param string  $declaredInterval  périodicité déclarée au submit (repli pour router un acompte)
     * @param int     $alreadyPaidMinor  cumul des acomptes NON SOLDÉS déjà approuvés sur la même cible
     * @param bool    $hasPromo          un code promo est appliqué → cible nette incertaine → needs_review
     */
    public function resolve(
        Plan $plan,
        int $amountMinor,
        string $currency,
        ?string $marketHint = null,
        string $declaredInterval = 'monthly',
        int $alreadyPaidMinor = 0,
        bool $hasPromo = false,
    ): PaymentPeriodResult {
        $currency         = strtoupper(trim($currency));
        $declaredInterval = in_array($declaredInterval, ['monthly', 'yearly'], true) ? $declaredInterval : 'monthly';
        $cumul            = max(0, $alreadyPaidMinor) + max(0, $amountMinor);

        // ── Marché : devise inconnue → fail-safe « unmatched » (jamais d'exception) ──────────
        [$market, $source] = $this->resolveMarket($currency, $marketHint);
        if ($market === null) {
            return new PaymentPeriodResult('global', 'unmatched', null, null, $cumul, false, false, 0, 0, 'unmatched');
        }

        // ── Cibles : BASE seule (sièges supplémentaires hors périmètre RC-1C) ────────────────
        $monthly = (int) ($plan->priceForMarket($market, 'monthly')?->base_amount_minor ?? 0);
        $yearly  = (int) ($plan->priceForMarket($market, 'yearly')?->base_amount_minor ?? 0);

        // ── Plan gratuit : activation immédiate, tout versement = avoir ───────────────────────
        if ($monthly === 0 && $yearly === 0) {
            return new PaymentPeriodResult($market, $source, 'monthly', 0, $cumul, true, false, 0, $cumul, 'free');
        }

        // ── Promo : la cible nette dépend de la remise → l'admin tranche ─────────────────────
        if ($hasPromo) {
            return new PaymentPeriodResult($market, $source, null, null, $cumul, false, false, 0, 0, 'needs_review');
        }

        // ── Solde d'une cible (annuel d'abord ; les bandes ±1 % ne se recoupent jamais car
        //    annuel = 10× mensuel) ──────────────────────────────────────────────────────────
        if ($yearly > 0 && $this->matches($cumul, $yearly)) {
            return $this->settled($market, $source, 'yearly', $yearly, $cumul);
        }
        if ($monthly > 0 && $this->matches($cumul, $monthly)) {
            return $this->settled($market, $source, 'monthly', $monthly, $cumul);
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
    private function settled(string $market, string $source, string $interval, int $target, int $cumul): PaymentPeriodResult
    {
        return new PaymentPeriodResult($market, $source, $interval, $target, $cumul, true, false, 0, 0, 'matched');
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
