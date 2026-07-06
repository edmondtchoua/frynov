<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\AccountingPeriod;
use App\Modules\Accounting\Models\Entry;
use App\Modules\Accounting\Models\FiscalYear;
use App\Modules\Accounting\Models\Journal;
use App\Modules\Platform\Services\AuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * RC-41 (P4.4) — clôture d'exercice + report-à-nouveau (RAN).
 *
 * À la clôture d'un exercice N : le **résultat** (soldes des comptes de gestion, classes 6-8) est
 * basculé sur le compte **13** (résultat net), et une écriture de **report-à-nouveau** est postée à
 * l'ouverture de l'exercice N+1 — elle reprend les soldes des comptes permanents (bilan, classes 1-5)
 * augmentés du résultat sur 13. L'exercice N+1 est **ouvert automatiquement** s'il n'existe pas.
 * L'exercice N passe `closed`, ses périodes `closed`, et pointe le RAN via `carry_forward_entry_id`.
 *
 * L'écriture RAN est équilibrée par construction : Σ soldes permanents = −Σ soldes de résultat
 * (la balance générale est équilibrée), et le résultat porté sur 13 referme l'écart.
 */
class ClosingService
{
    private const PERMANENT = [1, 2, 3, 4, 5]; // comptes de bilan (reportés)
    private const RESULT    = [6, 7, 8];       // comptes de gestion (→ résultat sur 13)

    public function __construct(
        private readonly LedgerService $ledger,
        private readonly EntryService $entries,
        private readonly AuditService $audit,
    ) {}

    public function close(FiscalYear $year, ?string $userId = null): array
    {
        if ($year->status === FiscalYear::STATUS_CLOSED) {
            throw ValidationException::withMessages(['fiscal_year' => ['Cet exercice est déjà clôturé.']]);
        }

        $tenantId = $year->tenant_id;

        // Balance de clôture (à-nouveau du RAN = soldes de fin d'exercice).
        $tb = $this->ledger->trialBalance($tenantId, $year->starts_on->toDateString(), $year->ends_on->toDateString());

        $result = Account::withoutTenantScope()->where('tenant_id', $tenantId)->where('code', '13')->first();
        if (! $result) {
            throw ValidationException::withMessages(['fiscal_year' => ['Compte de résultat 13 introuvable — initialisez le référentiel.']]);
        }

        $accounts = Account::withoutTenantScope()->where('tenant_id', $tenantId)
            ->whereIn('id', array_column($tb['rows'], 'account_id'))->get()->keyBy('id');

        $lines       = [];
        $resultSum   = 0; // Σ soldes de clôture des comptes de gestion (signé, débit positif)
        $acc13Close  = 0;
        foreach ($tb['rows'] as $row) {
            $closing = (int) $row['closing_minor'];
            if ($closing === 0) {
                continue;
            }
            $class = (int) ($accounts[$row['account_id']]->class_code ?? 0);

            if (in_array($class, self::RESULT, true)) {
                $resultSum += $closing; // basculé sur 13
            } elseif ($row['account_id'] === $result->id) {
                $acc13Close = $closing; // 13 traité à part (on y ajoute le résultat)
            } elseif (in_array($class, self::PERMANENT, true)) {
                $lines[] = $this->ranLine($row['account_id'], $closing, $row['code']);
            }
            // classe 9 (analytique) / hors bilan : ignorée
        }

        $val13 = $acc13Close + $resultSum;
        if ($val13 !== 0) {
            $lines[] = $this->ranLine($result->id, $val13, '13');
        }

        if (count($lines) < 2) {
            throw ValidationException::withMessages(['fiscal_year' => ['Aucun solde à reporter — exercice sans mouvement comptabilisé.']]);
        }

        $journal = Journal::withoutTenantScope()->where('tenant_id', $tenantId)->where('code', 'OD')->firstOrFail();

        return DB::transaction(function () use ($year, $tenantId, $lines, $journal, $resultSum, $userId) {
            $next = $this->ensureNextYear($year);

            $entry = $this->entries->createDraft([
                'journal_id' => $journal->id,
                'entry_date' => $next->starts_on->toDateString(),
                'label'      => 'Report à nouveau — ouverture ' . $next->label,
                'rule_code'  => 'closing.carry_forward',
                'lines'      => $lines,
            ], $tenantId, $userId);
            $entry = $this->entries->post($entry, $userId);

            AccountingPeriod::withoutTenantScope()->where('tenant_id', $tenantId)
                ->where('fiscal_year_id', $year->id)
                ->update(['status' => AccountingPeriod::STATUS_CLOSED]);

            $year->update([
                'status'                 => FiscalYear::STATUS_CLOSED,
                'closed_by'              => $userId,
                'closed_at'              => now(),
                'carry_forward_entry_id' => $entry->id,
            ]);

            $this->audit->log(
                action: 'accounting.fiscal_year.closed',
                tenantId: $tenantId,
                userId: $userId,
                subject: $year,
                newValues: ['carry_forward_entry_id' => $entry->id, 'ran_number' => $entry->number, 'result_minor' => -$resultSum, 'next_year' => $next->label],
            );

            return [
                'fiscal_year'         => $year->fresh(),
                'next_year'           => $next->fresh(),
                'carry_forward_entry' => $entry->fresh('lines'),
                'result_minor'        => -$resultSum, // >0 = bénéfice
            ];
        });
    }

    /** Exercice suivant : le récupère, ou l'ouvre (12 périodes mensuelles) s'il n'existe pas. */
    private function ensureNextYear(FiscalYear $year): FiscalYear
    {
        $existing = FiscalYear::withoutTenantScope()
            ->where('tenant_id', $year->tenant_id)
            ->where('starts_on', '>', $year->ends_on->toDateString())
            ->orderBy('starts_on')->first();
        if ($existing) {
            return $existing;
        }

        $start = $year->ends_on->copy()->addDay()->startOfDay();
        $end   = $start->copy()->addYear()->subDay()->startOfDay();

        $next = FiscalYear::withoutTenantScope()->create([
            'tenant_id' => $year->tenant_id,
            'label'     => $start->format('Y'),
            'starts_on' => $start->toDateString(),
            'ends_on'   => $end->toDateString(),
            'status'    => FiscalYear::STATUS_OPEN,
        ]);
        for ($m = 0; $m < 12; $m++) {
            $pStart = $start->copy()->addMonths($m);
            AccountingPeriod::withoutTenantScope()->create([
                'tenant_id'      => $year->tenant_id,
                'fiscal_year_id' => $next->id,
                'label'          => $pStart->format('Y-m'),
                'starts_on'      => $pStart->toDateString(),
                'ends_on'        => $pStart->copy()->endOfMonth()->startOfDay()->toDateString(),
                'status'         => AccountingPeriod::STATUS_OPEN,
            ]);
        }

        return $next;
    }

    private function ranLine(string $accountId, int $signedClosing, string $code): array
    {
        // Solde signé (débit positif) → débit si > 0, crédit si < 0.
        return [
            'account_id'   => $accountId,
            'label'        => 'Report à nouveau ' . $code,
            'debit_minor'  => $signedClosing > 0 ? $signedClosing : 0,
            'credit_minor' => $signedClosing < 0 ? -$signedClosing : 0,
        ];
    }
}
