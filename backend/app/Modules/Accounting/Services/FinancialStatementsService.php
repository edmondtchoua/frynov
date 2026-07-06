<?php

namespace App\Modules\Accounting\Services;

/**
 * RC-42 (P5) — états financiers SYSCOHADA (lecture), bâtis sur la balance générale.
 *
 * - Compte de résultat : charges (classe 6) vs produits (classe 7), classe 8 (HAO) ventilée par sens ;
 *   résultat = produits − charges.
 * - Bilan : actif = comptes permanents (classes 1-5) débiteurs ; passif = comptes permanents
 *   créditeurs + **résultat de l'exercice**. Équilibré par construction : la balance étant équilibrée,
 *   Σ soldes permanents = −Σ soldes de gestion = résultat, donc Actif = Passif + Résultat.
 *
 * Montants présentés en valeur absolue par côté (l'orientation est portée par la structure du rapport).
 */
class FinancialStatementsService
{
    public function __construct(private readonly LedgerService $ledger) {}

    /** Compte de résultat sur la période [from, to]. */
    public function incomeStatement(string $tenantId, ?string $from, string $to): array
    {
        $tb = $this->ledger->trialBalance($tenantId, $from, $to);

        $charges = [];
        $produits = [];
        foreach ($tb['rows'] as $row) {
            $class   = (int) substr((string) $row['code'], 0, 1);
            $closing = (int) $row['closing_minor'];
            if (! in_array($class, [6, 7, 8], true)) {
                continue;
            }
            // Charge = solde débiteur (closing > 0) ; produit = solde créditeur (closing < 0).
            if ($closing > 0) {
                $charges[] = $this->line($row, $closing, $class);
            } elseif ($closing < 0) {
                $produits[] = $this->line($row, -$closing, $class);
            }
        }

        $totalCharges  = array_sum(array_column($charges, 'amount_minor'));
        $totalProduits = array_sum(array_column($produits, 'amount_minor'));

        return [
            'charges'              => $charges,
            'produits'             => $produits,
            'total_charges_minor'  => $totalCharges,
            'total_produits_minor' => $totalProduits,
            'result_minor'         => $totalProduits - $totalCharges, // >0 = bénéfice
            'from'                 => $from,
            'to'                   => $to,
        ];
    }

    /** Bilan à la date [to] (à-nouveau depuis [from]). */
    public function balanceSheet(string $tenantId, ?string $from, string $to): array
    {
        $tb = $this->ledger->trialBalance($tenantId, $from, $to);

        $actif  = [];
        $passif = [];
        $result = 0; // résultat = −Σ soldes de gestion (classes 6-8)
        foreach ($tb['rows'] as $row) {
            $class   = (int) substr((string) $row['code'], 0, 1);
            $closing = (int) $row['closing_minor'];

            if (in_array($class, [6, 7, 8], true)) {
                $result -= $closing;
                continue;
            }
            if (! in_array($class, [1, 2, 3, 4, 5], true)) {
                continue; // classe 9 : hors bilan
            }
            if ($closing > 0) {
                $actif[] = $this->line($row, $closing, $class);
            } elseif ($closing < 0) {
                $passif[] = $this->line($row, -$closing, $class);
            }
        }

        // Le résultat de l'exercice figure au passif (capitaux propres) — négatif si perte.
        if ($result !== 0) {
            $passif[] = ['code' => '13', 'name' => 'Résultat de l\'exercice', 'class' => 1, 'amount_minor' => $result];
        }

        $totalActif  = array_sum(array_column($actif, 'amount_minor'));
        $totalPassif = array_sum(array_column($passif, 'amount_minor'));

        return [
            'actif'              => $actif,
            'passif'             => $passif,
            'total_actif_minor'  => $totalActif,
            'total_passif_minor' => $totalPassif,
            'result_minor'       => $result,
            'balanced'           => $totalActif === $totalPassif,
            'from'               => $from,
            'to'                 => $to,
        ];
    }

    private function line(array $row, int $amount, int $class): array
    {
        return ['code' => $row['code'], 'name' => $row['name'], 'class' => $class, 'amount_minor' => $amount];
    }
}
