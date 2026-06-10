<?php

namespace App\Modules\Billing\Services;

use App\Modules\Billing\Support\Markets;
use Carbon\CarbonInterface;

/**
 * Calcule le RELIQUAT (avoir du temps non consommé) du plan courant lors d'un changement de plan,
 * puis l'impute sur le nouveau plan (modèle hybride : déduit du net à payer, l'excédent part en avoir).
 *
 * Classe PURE : aucun I/O, horloge injectée (`$asOf`). Tous les montants en unités MINEURES.
 *
 * Durcie par revue adverse (workflow RC-2) :
 *  - **arithmétique ENTIÈRE** pour la fraction (déterministe cross-plateforme) + court-circuit aux
 *    bornes (jour 1 → crédit = assiette exact ; après expiration → 0) — aucune erreur flottante ne
 *    décide d'un montant ;
 *  - **assiette = payé − trop-perçu** déjà tracé (RC-1C) → pas de double comptage de l'avoir ;
 *  - **garde cross-devise** : un avoir n'est JAMAIS imputé sur une facture d'une autre devise ;
 *  - l'avoir reporté antérieur (`carried`, même devise) s'impute même quand le crédit de temps est nul.
 */
final class ProrationCalculator
{
    /**
     * @param int $amountPaidMinor   montant réellement encaissé pour la période courante
     * @param int $overpaidMinor     trop-perçu déjà tracé (metadata['overpaid_minor']) — retiré de l'assiette
     * @param int $newGrossMinor     tarif plein du nouveau plan/interval/marché
     * @param int $carriedCreditMinor avoir déjà reporté (metadata['credit_minor']) à ré-imputer (même devise)
     */
    public function compute(
        int $amountPaidMinor,
        int $overpaidMinor,
        ?CarbonInterface $periodStart,
        ?CarbonInterface $periodEnd,
        string $status,
        string $currentCurrency,
        int $newGrossMinor,
        string $newCurrency,
        CarbonInterface $asOf,
        int $carriedCreditMinor = 0,
    ): ProrationResult {
        $exp      = Markets::exponentForCurrency($currentCurrency);
        $newGross = max(0, $newGrossMinor);
        $carried  = max(0, $carriedCreditMinor);
        // Assiette NETTE : le trop-perçu RC-1C est déjà un avoir séparé → ne pas le recompter ici.
        $assiette = max(0, $amountPaidMinor - max(0, $overpaidMinor));

        // ── Garde cross-devise : un avoir ne franchit jamais une frontière de devise ──────────
        if ($currentCurrency !== $newCurrency) {
            return new ProrationResult(
                false, 0.0, $currentCurrency, $exp,
                0, 0, $carried,            // avoir préservé en devise d'origine, NON imputé
                $newGross, $newGross, 'cross_currency_blocked',
            );
        }

        // ── Gardes d'éligibilité : crédit de temps = 0, mais l'avoir reporté (même devise) s'impute ──
        $ineligible = $this->ineligibilityReason($assiette, $periodStart, $periodEnd, $status, $asOf);
        if ($ineligible !== null) {
            return $this->settle($currentCurrency, $exp, 0, 0.0, $carried, $newGross, $ineligible);
        }

        // ── Fraction de temps NON consommé, en arithmétique ENTIÈRE (secondes) ───────────────
        $total     = $periodEnd->getTimestamp() - $periodStart->getTimestamp();
        $remaining = max(0, min($periodEnd->getTimestamp() - $asOf->getTimestamp(), $total));

        if ($remaining >= $total) {
            $credit = $assiette;                       // jour 1 : tout le temps reste (exact)
        } elseif ($remaining <= 0) {
            $credit = 0;
        } else {
            // round-half-up déterministe : floor((assiette*remaining + total/2) / total)
            $credit = intdiv($assiette * $remaining + intdiv($total, 2), $total);
        }
        $credit   = max(0, min($credit, $assiette));   // bornage dur
        $fraction = round($remaining / $total, 6);

        return $this->settle($currentCurrency, $exp, $credit, $fraction, $carried, $newGross, null, $assiette, true);
    }

    /** @return string|null motif d'inéligibilité (ordre strict), ou null si éligible. */
    private function ineligibilityReason(int $assiette, ?CarbonInterface $start, ?CarbonInterface $end, string $status, CarbonInterface $asOf): ?string
    {
        if ($assiette <= 0) {
            return 'not_paid';                 // trial / gratuit / jamais encaissé
        }
        if ($end === null || $start === null) {
            return 'past_due_no_period';       // acompte non soldé : période non démarrée
        }
        if ($status !== 'active') {
            return 'not_eligible';             // trialing/suspended/cancelled/pending_approval
        }
        if ($end->getTimestamp() - $start->getTimestamp() <= 0) {
            return 'degenerate_period';
        }
        if ($asOf->getTimestamp() >= $end->getTimestamp()) {
            return 'expired';                  // rien de non consommé
        }

        return null;
    }

    /**
     * Construit le résultat en imputant (crédit de temps + avoir reporté) sur le nouveau tarif.
     * Le motif `ok|downgrade|free_target` est déduit du sens quand éligible.
     */
    private function settle(
        string $currency,
        int $exp,
        int $credit,
        float $fraction,
        int $carried,
        int $newGross,
        ?string $forcedReason,
        int $assiette = 0,
        bool $eligible = false,
    ): ProrationResult {
        $totalCredit = $credit + $carried;
        $applied     = min($totalCredit, $newGross);
        $net         = $newGross - $applied;
        $carry       = $totalCredit - $applied;

        $reason = $forcedReason ?? (
            $newGross === 0 ? 'free_target'
            : ($newGross < $assiette ? 'downgrade' : 'ok')
        );

        return new ProrationResult(
            $eligible, $fraction, $currency, $exp,
            $credit, $applied, $carry,
            $newGross, $net, $reason,
        );
    }
}
