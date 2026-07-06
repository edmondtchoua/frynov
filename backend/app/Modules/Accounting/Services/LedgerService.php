<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Entry;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * RC-38 (P4.1) — états de lecture sur les écritures comptabilisées.
 *
 * Ne lit QUE les écritures `posted` **et** `reversed` : une écriture extournée reste un mouvement
 * réel (son extourne, elle aussi `posted`, la contre-passe) — les exclure fausserait les soldes.
 * Les brouillons sont ignorés. Montants signés en centimes (débit positif : un solde > 0 est
 * débiteur, < 0 créditeur).
 */
class LedgerService
{
    private const POSTED_STATES = [Entry::STATUS_POSTED, Entry::STATUS_REVERSED];

    /**
     * Balance générale : par compte mouvementé, à-nouveau (avant `from`) + mouvements de la période
     * + solde. Invariants (chaque écriture équilibrée) : Σ débits = Σ crédits et
     * Σ soldes débiteurs = Σ soldes créditeurs.
     */
    public function trialBalance(string $tenantId, ?string $from, string $to): array
    {
        // À-nouveau : solde net (débit − crédit) strictement avant `from`.
        $opening = [];
        if ($from) {
            $rows = $this->baseQuery($tenantId)
                ->whereDate('e.entry_date', '<', $from)
                ->groupBy('l.account_id')
                ->select('l.account_id', DB::raw('SUM(l.debit_minor - l.credit_minor) as net'))
                ->get();
            foreach ($rows as $r) {
                $opening[$r->account_id] = (int) $r->net;
            }
        }

        // Mouvements de la période, débit et crédit séparés.
        $period = $this->baseQuery($tenantId)
            ->when($from, fn ($q) => $q->whereDate('e.entry_date', '>=', $from))
            ->whereDate('e.entry_date', '<=', $to)
            ->groupBy('l.account_id')
            ->select('l.account_id', DB::raw('SUM(l.debit_minor) as d'), DB::raw('SUM(l.credit_minor) as c'))
            ->get()->keyBy('account_id');

        $accountIds = collect(array_keys($opening))->merge($period->keys())->unique()->values()->all();
        if (empty($accountIds)) {
            return ['rows' => [], 'totals' => $this->zeroTotals(), 'from' => $from, 'to' => $to];
        }

        $accounts = Account::withoutTenantScope()->where('tenant_id', $tenantId)
            ->whereIn('id', $accountIds)->get()->keyBy('id');

        $out = [];
        foreach ($accountIds as $id) {
            $acc = $accounts[$id] ?? null;
            if (! $acc) {
                continue;
            }
            $open  = $opening[$id] ?? 0;
            $d     = (int) ($period[$id]->d ?? 0);
            $c     = (int) ($period[$id]->c ?? 0);
            $close = $open + $d - $c;

            if ($open === 0 && $d === 0 && $c === 0) {
                continue; // compte sans mouvement ni à-nouveau
            }

            $out[] = [
                'account_id'    => $id,
                'code'          => $acc->code,
                'name'          => $acc->name,
                'kind'          => $acc->kind,
                'opening_minor' => $open,
                'debit_minor'   => $d,
                'credit_minor'  => $c,
                'closing_minor' => $close,
            ];
        }
        usort($out, fn ($a, $b) => strcmp($a['code'], $b['code']));

        $totals = [
            'debit_minor'          => array_sum(array_column($out, 'debit_minor')),
            'credit_minor'         => array_sum(array_column($out, 'credit_minor')),
            'closing_debit_minor'  => array_sum(array_map(fn ($r) => max(0, $r['closing_minor']), $out)),
            'closing_credit_minor' => array_sum(array_map(fn ($r) => max(0, -$r['closing_minor']), $out)),
        ];

        return ['rows' => $out, 'totals' => $totals, 'from' => $from, 'to' => $to];
    }

    /**
     * Grand livre d'un compte : à-nouveau + lignes ordonnées (date, journal) avec solde progressif,
     * mouvements et solde de clôture.
     */
    public function generalLedger(string $tenantId, string $accountId, ?string $from, string $to): array
    {
        $account = Account::withoutTenantScope()->where('tenant_id', $tenantId)->findOrFail($accountId);

        $opening = 0;
        if ($from) {
            $opening = (int) $this->baseQuery($tenantId)
                ->where('l.account_id', $accountId)
                ->whereDate('e.entry_date', '<', $from)
                ->select(DB::raw('COALESCE(SUM(l.debit_minor - l.credit_minor), 0) as net'))
                ->value('net');
        }

        $rows = $this->baseQuery($tenantId)
            ->join('accounting_journals as j', 'j.id', '=', 'e.journal_id')
            ->where('l.account_id', $accountId)
            ->when($from, fn ($q) => $q->whereDate('e.entry_date', '>=', $from))
            ->whereDate('e.entry_date', '<=', $to)
            ->orderBy('e.entry_date')->orderBy('e.number')
            ->select('e.entry_date', 'e.number', 'e.label', 'j.code as journal', 'l.label as line_label', 'l.debit_minor', 'l.credit_minor')
            ->get();

        $running = $opening;
        $dTot = 0;
        $cTot = 0;
        $lines = [];
        foreach ($rows as $r) {
            $d = (int) $r->debit_minor;
            $c = (int) $r->credit_minor;
            $running += $d - $c;
            $dTot += $d;
            $cTot += $c;
            $lines[] = [
                'date'          => $r->entry_date,
                'number'        => $r->number,
                'journal'       => $r->journal,
                'label'         => $r->line_label ?: $r->label,
                'debit_minor'   => $d,
                'credit_minor'  => $c,
                'running_minor' => $running,
            ];
        }

        return [
            'account'            => ['id' => $account->id, 'code' => $account->code, 'name' => $account->name, 'kind' => $account->kind],
            'opening_minor'      => $opening,
            'debit_total_minor'  => $dTot,
            'credit_total_minor' => $cTot,
            'closing_minor'      => $running,
            'lines'              => $lines,
        ];
    }

    private function baseQuery(string $tenantId): Builder
    {
        return DB::table('accounting_entry_lines as l')
            ->join('accounting_entries as e', 'e.id', '=', 'l.entry_id')
            ->where('l.tenant_id', $tenantId)
            ->whereIn('e.status', self::POSTED_STATES);
    }

    private function zeroTotals(): array
    {
        return ['debit_minor' => 0, 'credit_minor' => 0, 'closing_debit_minor' => 0, 'closing_credit_minor' => 0];
    }
}
