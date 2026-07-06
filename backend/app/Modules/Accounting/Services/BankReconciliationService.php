<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Entry;
use App\Modules\Accounting\Models\EntryLine;
use App\Modules\Platform\Services\AuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * RC-43 (P4.3) — rapprochement bancaire par pointage.
 *
 * Les écritures d'un compte de banque (521…) sont pointées (`pointed`) lorsqu'elles figurent sur le
 * relevé bancaire. Les lignes NON pointées sont les « en-cours » : dépôts en transit (débits) et
 * chèques en circulation (crédits). L'état de rapprochement vérifie l'identité classique :
 *   solde comptable = solde du relevé + dépôts en transit − chèques en circulation.
 */
class BankReconciliationService
{
    private const POSTED_STATES = [Entry::STATUS_POSTED, Entry::STATUS_REVERSED];

    public function __construct(private readonly AuditService $audit) {}

    /** État de rapprochement d'un compte de banque (+ contrôle si le solde de relevé est fourni). */
    public function state(string $tenantId, string $accountId, ?int $statementBalanceMinor = null): array
    {
        $account = Account::withoutTenantScope()->where('tenant_id', $tenantId)->findOrFail($accountId);

        $rows = DB::table('accounting_entry_lines as l')
            ->join('accounting_entries as e', 'e.id', '=', 'l.entry_id')
            ->join('accounting_journals as j', 'j.id', '=', 'e.journal_id')
            ->where('l.tenant_id', $tenantId)
            ->where('l.account_id', $accountId)
            ->whereIn('e.status', self::POSTED_STATES)
            ->orderBy('e.entry_date')->orderBy('e.number')
            ->select('l.id', 'e.entry_date', 'e.number', 'j.code as journal', 'l.label', 'l.debit_minor', 'l.credit_minor', 'l.pointed')
            ->get();

        $book = 0;
        $pointedBalance = 0;
        $outDebit = 0;
        $outCredit = 0;
        $lines = [];
        foreach ($rows as $r) {
            $d = (int) $r->debit_minor;
            $c = (int) $r->credit_minor;
            $book += $d - $c;
            if ($r->pointed) {
                $pointedBalance += $d - $c;
            } else {
                $outDebit += $d;
                $outCredit += $c;
            }
            $lines[] = [
                'id' => $r->id, 'date' => $r->entry_date, 'number' => $r->number, 'journal' => $r->journal,
                'label' => $r->label, 'debit_minor' => $d, 'credit_minor' => $c, 'pointed' => (bool) $r->pointed,
            ];
        }

        $summary = [
            'book_balance_minor'        => $book,           // solde comptable du 521
            'pointed_balance_minor'     => $pointedBalance, // mouvements pointés
            'outstanding_debit_minor'   => $outDebit,       // dépôts en transit (en-cours)
            'outstanding_credit_minor'  => $outCredit,      // chèques en circulation (en-cours)
        ];
        if ($statementBalanceMinor !== null) {
            $theoretical = $statementBalanceMinor + $outDebit - $outCredit;
            $summary['statement_balance_minor'] = $statementBalanceMinor;
            $summary['difference_minor'] = $book - $theoretical; // 0 = rapproché
            $summary['reconciled'] = ($book - $theoretical) === 0;
        }

        return ['account' => ['id' => $account->id, 'code' => $account->code, 'name' => $account->name], 'lines' => $lines, 'summary' => $summary];
    }

    /** Pointe (ou dé-pointe) des lignes d'un compte de banque. */
    public function setPointed(string $tenantId, string $accountId, array $lineIds, bool $pointed, ?string $userId = null): int
    {
        $lineIds = array_values(array_unique($lineIds));
        if (empty($lineIds)) {
            throw ValidationException::withMessages(['line_ids' => ['Aucune ligne sélectionnée.']]);
        }

        // Ne pointer que des lignes du compte, issues d'écritures comptabilisées/extournées.
        $validIds = DB::table('accounting_entry_lines as l')
            ->join('accounting_entries as e', 'e.id', '=', 'l.entry_id')
            ->where('l.tenant_id', $tenantId)
            ->where('l.account_id', $accountId)
            ->whereIn('l.id', $lineIds)
            ->whereIn('e.status', self::POSTED_STATES)
            ->pluck('l.id')->all();

        if (count($validIds) !== count($lineIds)) {
            throw ValidationException::withMessages(['line_ids' => ['Certaines lignes sont introuvables, hors du compte ou non comptabilisées.']]);
        }

        $count = EntryLine::withoutTenantScope()->whereIn('id', $validIds)
            ->update(['pointed' => $pointed, 'pointed_at' => $pointed ? now() : null]);

        $this->audit->log(
            action: $pointed ? 'accounting.bank.pointed' : 'accounting.bank.unpointed',
            tenantId: $tenantId,
            userId: $userId,
            newValues: ['account_id' => $accountId, 'lines' => $count],
        );

        return $count;
    }
}
