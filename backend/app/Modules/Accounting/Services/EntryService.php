<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Models\AccountingPeriod;
use App\Modules\Accounting\Models\Entry;
use App\Modules\Accounting\Models\Journal;
use App\Modules\Platform\Services\AuditService;
use App\Shared\Services\SequenceService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * RC-25 — cycle de vie des écritures : brouillon → post (numéro + période) → extourne.
 *
 * Garde-fous structurels :
 *  - ÉQUILIBRE : Σ débits = Σ crédits, ≥ 2 lignes, chaque ligne porte exactement un côté > 0 ;
 *  - PÉRIODE : l'entry_date doit tomber dans une période OUVERTE (verrouillée/close → 422) ;
 *  - IMMUTABILITÉ : une écriture posted ne se modifie ni ne se supprime — extourne uniquement.
 */
class EntryService
{
    public function __construct(
        private readonly SequenceService $sequences,
        private readonly AuditService $audit,
    ) {}

    /**
     * Crée un brouillon équilibré.
     *
     * @param array $data  ['journal_id','entry_date','label','currency'?,'lines'=>[{account_id,label?,debit_minor,credit_minor,third_party_*?}],
     *                      'source_type'?,'source_id'?,'rule_code'?,'inputs_snapshot'?]
     */
    public function createDraft(array $data, string $tenantId, ?string $userId = null): Entry
    {
        $lines = $data['lines'] ?? [];
        $this->assertBalanced($lines);
        $this->resolveOpenPeriod($tenantId, $data['entry_date']); // refuse tôt une période fermée

        return DB::transaction(function () use ($data, $lines, $tenantId, $userId) {
            $entry = Entry::create([
                'tenant_id'       => $tenantId,
                'journal_id'      => $data['journal_id'],
                'entry_date'      => $data['entry_date'],
                'label'           => $data['label'],
                'currency'        => $data['currency'] ?? 'XOF',
                'status'          => Entry::STATUS_DRAFT,
                'source_type'     => $data['source_type'] ?? null,
                'source_id'       => $data['source_id'] ?? null,
                'rule_code'       => $data['rule_code'] ?? null,
                'inputs_snapshot' => $data['inputs_snapshot'] ?? null,
                'created_by'      => $userId,
            ]);

            foreach ($lines as $l) {
                $entry->lines()->create([
                    'tenant_id'        => $tenantId,
                    'account_id'       => $l['account_id'],
                    'label'            => $l['label'] ?? null,
                    'debit_minor'      => (int) ($l['debit_minor'] ?? 0),
                    'credit_minor'     => (int) ($l['credit_minor'] ?? 0),
                    'tax_id'           => $l['tax_id'] ?? null,
                    'third_party_type' => $l['third_party_type'] ?? null,
                    'third_party_id'   => $l['third_party_id'] ?? null,
                ]);
            }

            return $entry->load('lines');
        });
    }

    /** Comptabilise : n° séquentiel par journal + rattachement à la période/exercice. */
    public function post(Entry $entry, ?string $userId = null): Entry
    {
        if ($entry->status !== Entry::STATUS_DRAFT) {
            throw ValidationException::withMessages([
                'entry' => ['Seul un brouillon peut être comptabilisé.'],
            ]);
        }

        $entry->load('lines');
        $this->assertBalanced($entry->lines->map(fn ($l) => [
            'account_id' => $l->account_id, 'debit_minor' => $l->debit_minor, 'credit_minor' => $l->credit_minor,
        ])->all());

        $period = $this->resolveOpenPeriod($entry->tenant_id, $entry->entry_date->toDateString());

        return DB::transaction(function () use ($entry, $period, $userId) {
            $journal = Journal::withoutTenantScope()->findOrFail($entry->journal_id);
            $number  = $this->sequences->next($entry->tenant_id, $journal->sequence_prefix, 6);

            $entry->update([
                'status'         => Entry::STATUS_POSTED,
                'number'         => $number,
                'period_id'      => $period->id,
                'fiscal_year_id' => $period->fiscal_year_id,
                'posted_by'      => $userId,
                'posted_at'      => now(),
            ]);

            $this->audit->log(
                action: 'accounting.entry.posted',
                tenantId: $entry->tenant_id,
                userId: $userId,
                subject: $entry,
                newValues: ['number' => $number, 'total' => $entry->totalDebit()],
            );

            return $entry->fresh('lines');
        });
    }

    /**
     * Extourne une écriture POSTED : contre-écriture (côtés inversés) datée du jour (ou de la
     * date fournie si sa période est ouverte), postée immédiatement, liée dans les deux sens.
     */
    public function reverse(Entry $entry, ?string $userId = null, ?string $date = null, ?string $reason = null): Entry
    {
        if (! $entry->isPosted()) {
            throw ValidationException::withMessages([
                'entry' => ['Seule une écriture comptabilisée peut être extournée.'],
            ]);
        }
        if ($entry->reversed_by_id !== null) {
            throw ValidationException::withMessages([
                'entry' => ['Cette écriture a déjà été extournée.'],
            ]);
        }

        $entry->load('lines');
        $reversalDate = $date ?? now()->toDateString();

        return DB::transaction(function () use ($entry, $reversalDate, $userId, $reason) {
            $reversal = $this->createDraft([
                'journal_id'  => $entry->journal_id,
                'entry_date'  => $reversalDate,
                'label'       => 'EXTOURNE ' . ($entry->number ?? '') . ($reason ? " — {$reason}" : '') . ' : ' . $entry->label,
                'currency'    => $entry->currency,
                'source_type' => $entry->source_type,
                'source_id'   => $entry->source_id,
                'rule_code'   => $entry->rule_code ? $entry->rule_code . '.reversal' : 'manual.reversal',
                'lines'       => $entry->lines->map(fn ($l) => [
                    'account_id'   => $l->account_id,
                    'label'        => $l->label,
                    'debit_minor'  => $l->credit_minor,   // côtés INVERSÉS
                    'credit_minor' => $l->debit_minor,
                    'third_party_type' => $l->third_party_type,
                    'third_party_id'   => $l->third_party_id,
                ])->all(),
            ], $entry->tenant_id, $userId);

            $reversal->update(['reversal_of_id' => $entry->id]);
            $reversal = $this->post($reversal, $userId);

            $entry->update(['status' => Entry::STATUS_REVERSED, 'reversed_by_id' => $reversal->id]);

            $this->audit->log(
                action: 'accounting.entry.reversed',
                tenantId: $entry->tenant_id,
                userId: $userId,
                subject: $entry,
                newValues: ['reversal_id' => $reversal->id, 'reversal_number' => $reversal->number, 'reason' => $reason],
            );

            return $reversal;
        });
    }

    // ── Garde-fous ─────────────────────────────────────────────────────────────

    /** @param array<int,array{account_id:string,debit_minor?:int|string,credit_minor?:int|string}> $lines */
    private function assertBalanced(array $lines): void
    {
        if (count($lines) < 2) {
            throw ValidationException::withMessages([
                'lines' => ['Une écriture comporte au moins deux lignes.'],
            ]);
        }

        $debit = 0;
        $credit = 0;
        foreach ($lines as $i => $l) {
            $d = (int) ($l['debit_minor'] ?? 0);
            $c = (int) ($l['credit_minor'] ?? 0);
            if (($d > 0) === ($c > 0)) { // les deux à 0, ou les deux > 0
                throw ValidationException::withMessages([
                    'lines' => ["Ligne " . ($i + 1) . " : exactement un côté (débit OU crédit) doit être strictement positif."],
                ]);
            }
            $debit  += $d;
            $credit += $c;
        }

        if ($debit !== $credit) {
            throw ValidationException::withMessages([
                'lines' => ["Écriture déséquilibrée : débits {$debit} ≠ crédits {$credit}."],
            ]);
        }
    }

    /** La période couvrant la date doit exister et être OUVERTE. */
    public function resolveOpenPeriod(string $tenantId, string $date): AccountingPeriod
    {
        $period = AccountingPeriod::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->where('starts_on', '<=', $date)
            ->where('ends_on', '>=', $date)
            ->first();

        if (! $period) {
            throw ValidationException::withMessages([
                'entry_date' => ["Aucune période comptable ne couvre la date {$date} — initialisez/ouvrez l'exercice."],
            ]);
        }
        if (! $period->isOpen()) {
            throw ValidationException::withMessages([
                'entry_date' => ["La période {$period->label} est " . ($period->status === 'locked' ? 'verrouillée' : 'close') . ' : aucune écriture ne peut y être datée.'],
            ]);
        }

        return $period;
    }
}
