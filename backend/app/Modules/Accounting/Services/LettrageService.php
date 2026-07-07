<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Entry;
use App\Modules\Accounting\Models\EntryLine;
use App\Modules\Platform\Services\AuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * RC-39 (P4.2) — lettrage des comptes de tiers.
 *
 * Rapproche des lignes d'un MÊME compte formant un groupe ÉQUILIBRÉ (Σ débits = Σ crédits) sous un
 * `lettrage_code` (A, B… par compte). Le non-lettré = le solde réellement ouvert. Ne porte que sur
 * des lignes d'écritures `posted`/`reversed`. Le lettrage est réversible (délettrage).
 */
class LettrageService
{
    private const POSTED_STATES = [Entry::STATUS_POSTED, Entry::STATUS_REVERSED];

    public function __construct(private readonly AuditService $audit) {}

    /**
     * Lignes lettrables d'un compte + synthèse (lettré / ouvert). `onlyOpen` masque le lettré.
     */
    public function accountLines(string $tenantId, string $accountId, bool $onlyOpen = false): array
    {
        $account = Account::withoutTenantScope()->where('tenant_id', $tenantId)->findOrFail($accountId);

        $rows = DB::table('accounting_entry_lines as l')
            ->join('accounting_entries as e', 'e.id', '=', 'l.entry_id')
            ->join('accounting_journals as j', 'j.id', '=', 'e.journal_id')
            ->where('l.tenant_id', $tenantId)
            ->where('l.account_id', $accountId)
            ->whereIn('e.status', self::POSTED_STATES)
            ->when($onlyOpen, fn ($q) => $q->whereNull('l.lettrage_code'))
            ->orderBy('e.entry_date')->orderBy('e.number')
            ->select('l.id', 'e.entry_date', 'e.number', 'j.code as journal', 'l.label', 'l.debit_minor', 'l.credit_minor', 'l.lettrage_code')
            ->get();

        $letteredD = 0;
        $letteredC = 0;
        $openD = 0;
        $openC = 0;
        $lines = [];
        foreach ($rows as $r) {
            $d = (int) $r->debit_minor;
            $c = (int) $r->credit_minor;
            if ($r->lettrage_code) {
                $letteredD += $d;
                $letteredC += $c;
            } else {
                $openD += $d;
                $openC += $c;
            }
            $lines[] = [
                'id' => $r->id, 'date' => $r->entry_date, 'number' => $r->number, 'journal' => $r->journal,
                'label' => $r->label, 'debit_minor' => $d, 'credit_minor' => $c, 'lettrage_code' => $r->lettrage_code,
            ];
        }

        return [
            'account'      => ['id' => $account->id, 'code' => $account->code, 'name' => $account->name],
            'lines'        => $lines,
            'summary'      => [
                'lettered_debit_minor'  => $letteredD,
                'lettered_credit_minor' => $letteredC,
                'open_debit_minor'      => $openD,
                'open_credit_minor'     => $openC,
                'open_balance_minor'    => $openD - $openC, // solde ouvert (signé, débiteur positif)
            ],
        ];
    }

    /**
     * Lettre un groupe de lignes équilibré (Σ débits = Σ crédits) d'un compte → nouveau code.
     *
     * @param string[] $lineIds
     */
    public function letter(string $tenantId, string $accountId, array $lineIds, ?string $userId = null): string
    {
        $lineIds = array_values(array_unique($lineIds));
        if (count($lineIds) < 2) {
            throw ValidationException::withMessages(['line_ids' => ['Sélectionnez au moins deux lignes à lettrer.']]);
        }

        $lines = EntryLine::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->where('account_id', $accountId)
            ->whereIn('id', $lineIds)
            ->whereNull('lettrage_code')
            ->get();

        if ($lines->count() !== count($lineIds)) {
            throw ValidationException::withMessages(['line_ids' => ['Certaines lignes sont introuvables, déjà lettrées ou hors du compte.']]);
        }

        // Les lignes doivent appartenir à des écritures comptabilisées/extournées.
        $validEntryIds = Entry::withoutTenantScope()
            ->whereIn('id', $lines->pluck('entry_id')->unique())
            ->whereIn('status', self::POSTED_STATES)
            ->pluck('id')->all();
        if ($lines->pluck('entry_id')->unique()->diff($validEntryIds)->isNotEmpty()) {
            throw ValidationException::withMessages(['line_ids' => ['Seules des écritures comptabilisées peuvent être lettrées.']]);
        }

        $debit  = (int) $lines->sum('debit_minor');
        $credit = (int) $lines->sum('credit_minor');
        if ($debit !== $credit) {
            throw ValidationException::withMessages([
                'line_ids' => ["Groupe déséquilibré : débits {$debit} ≠ crédits {$credit}. Le lettrage exige un groupe soldé."],
            ]);
        }
        if ($debit === 0) {
            throw ValidationException::withMessages(['line_ids' => ['Le groupe ne porte aucun montant.']]);
        }

        $code = $this->nextCode($tenantId, $accountId);

        DB::transaction(function () use ($lines, $code) {
            EntryLine::withoutTenantScope()->whereIn('id', $lines->pluck('id'))
                ->update(['lettrage_code' => $code, 'lettered_at' => now()]);
        });

        $this->audit->log(
            action: 'accounting.lettrage.lettered',
            tenantId: $tenantId,
            userId: $userId,
            newValues: ['account_id' => $accountId, 'code' => $code, 'lines' => $lines->count(), 'amount' => $debit],
        );

        return $code;
    }

    /** Délettre un groupe (efface le code sur ses lignes). */
    public function unletter(string $tenantId, string $accountId, string $code, ?string $userId = null): int
    {
        $count = EntryLine::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->where('account_id', $accountId)
            ->where('lettrage_code', $code)
            ->update(['lettrage_code' => null, 'lettered_at' => null]);

        if ($count === 0) {
            throw ValidationException::withMessages(['code' => ['Aucune ligne lettrée sous ce code pour ce compte.']]);
        }

        $this->audit->log(
            action: 'accounting.lettrage.unlettered',
            tenantId: $tenantId,
            userId: $userId,
            newValues: ['account_id' => $accountId, 'code' => $code, 'lines' => $count],
        );

        return $count;
    }

    // ── Codes de lettrage (A, B, … Z, AA, … par compte) ──────────────────────────

    private function nextCode(string $tenantId, string $accountId): string
    {
        $existing = EntryLine::withoutTenantScope()
            ->where('tenant_id', $tenantId)->where('account_id', $accountId)
            ->whereNotNull('lettrage_code')
            ->distinct()->pluck('lettrage_code');

        $max = 0;
        foreach ($existing as $c) {
            $max = max($max, $this->lettersToInt($c));
        }

        return $this->intToLetters($max + 1);
    }

    /** 1 → A, 26 → Z, 27 → AA (bijectif base 26). */
    private function intToLetters(int $n): string
    {
        $s = '';
        while ($n > 0) {
            $n--;
            $s = chr(65 + ($n % 26)) . $s;
            $n = intdiv($n, 26);
        }

        return $s;
    }

    private function lettersToInt(string $s): int
    {
        $n = 0;
        foreach (str_split($s) as $ch) {
            $n = $n * 26 + (ord($ch) - 64);
        }

        return $n;
    }
}
