<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\AccountingSettings;
use App\Modules\Accounting\Models\Entry;
use App\Modules\Accounting\Models\Journal;
use App\Modules\Accounting\Models\OutboxEvent;
use Illuminate\Support\Facades\DB;

/**
 * RC-26 — moteur d'imputation comptable.
 *
 * Flux : un événement métier est ENREGISTRÉ dans l'outbox (`record`, idempotent par contrainte
 * unique tenant+event+source — le rejeu offline/retry ne produit jamais deux écritures), puis
 * TRAITÉ (`process`) : résolution des comptes via `tenant_accounting_settings.default_accounts`
 * (références symboliques @cash/@sales/… → codes de comptes du tenant), génération d'une écriture
 * équilibrée tracée (source_*, rule_code, inputs_snapshot), brouillon ou postée selon `auto_post`.
 *
 * `record()` est appelé APRÈS la transaction métier et n'échoue JAMAIS bloquant : en cas d'erreur
 * de traitement, la ligne outbox reste `pending`/`failed` et sera rejouée par
 * `accounting:process-outbox`. Tenant non provisionné → aucun enregistrement (la comptabilité
 * démarre à l'initialisation du référentiel).
 *
 * Règles par défaut (SYSCOHADA) — configurables via les comptes par défaut du tenant :
 *   pos.sale          : débit trésorerie par leg (571/585/521) ; crédit @sales (701)
 *   pos.refund        : débit @sales ; crédit trésorerie (571/585/521 selon mode)
 *   pos.session_gap   : manquant → débit @cash_short (658) / crédit @cash ; surplus → inverse (758)
 *   cash.movement     : float_add 571↔521 ; withdrawal 521↔571 ; expense @suspense (471) / 571
 *   payment.recorded  : débit trésorerie ; crédit @customers (411) — paiement client hors POS
 */
class ImputationEngine
{
    public function __construct(private readonly EntryService $entries) {}

    /** Enregistre l'événement (idempotent) puis tente le traitement immédiat (best-effort). */
    public function record(string $tenantId, string $eventType, string $sourceType, string $sourceId, array $payload): ?OutboxEvent
    {
        try {
            if (! AccountingSettings::withoutTenantScope()->where('tenant_id', $tenantId)->exists()) {
                return null; // comptabilité non initialisée : rien à faire (rattrapage possible en P4)
            }

            $event = OutboxEvent::withoutTenantScope()->firstOrCreate(
                ['tenant_id' => $tenantId, 'event_type' => $eventType, 'source_type' => $sourceType, 'source_id' => $sourceId],
                ['payload' => $payload, 'status' => OutboxEvent::STATUS_PENDING],
            );

            if ($event->status === OutboxEvent::STATUS_PENDING) {
                $this->process($event); // best-effort ; l'échec laisse la ligne rejouable
            }

            return $event;
        } catch (\Throwable) {
            return null; // la génération comptable ne bloque JAMAIS le flux métier
        }
    }

    /** Rejoue les événements en attente (worker `accounting:process-outbox`). */
    public function processPending(int $limit = 100): array
    {
        $done = 0;
        $failed = 0;

        $batch = OutboxEvent::withoutTenantScope()
            ->where('status', OutboxEvent::STATUS_PENDING)
            ->where('attempts', '<', OutboxEvent::MAX_ATTEMPTS)
            ->oldest()->limit($limit)->get();

        foreach ($batch as $event) {
            $this->process($event) ? $done++ : $failed++;
        }

        return ['processed' => $done, 'failed' => $failed];
    }

    /** Traite UN événement : génère l'écriture (transactionnel) et marque l'outbox. */
    public function process(OutboxEvent $event): bool
    {
        try {
            $entry = DB::transaction(fn () => $this->buildEntry($event));

            $event->update([
                'status'   => $entry ? OutboxEvent::STATUS_PROCESSED : OutboxEvent::STATUS_SKIPPED,
                'entry_id' => $entry?->id,
                'attempts' => $event->attempts + 1,
            ]);

            return true;
        } catch (\Throwable $e) {
            $attempts = $event->attempts + 1;
            $event->update([
                'status'     => $attempts >= OutboxEvent::MAX_ATTEMPTS ? OutboxEvent::STATUS_FAILED : OutboxEvent::STATUS_PENDING,
                'attempts'   => $attempts,
                'last_error' => mb_substr($e->getMessage(), 0, 490),
            ]);

            return false;
        }
    }

    // ── Construction des écritures par règle ──────────────────────────────────

    private function buildEntry(OutboxEvent $event): ?Entry
    {
        $p        = $event->payload;
        $tenantId = $event->tenant_id;
        $currency = $p['currency'] ?? 'XOF';
        $date     = $p['date'] ?? now()->toDateString();

        [$journalCode, $label, $lines] = match ($event->event_type) {
            'pos.sale'          => $this->ruleForPosSale($tenantId, $p),
            'pos.refund'        => $this->ruleForPosRefund($tenantId, $p),
            'pos.session_gap'   => $this->ruleForSessionGap($tenantId, $p),
            'cash.movement'     => $this->ruleForCashMovement($tenantId, $p),
            'payment.recorded'  => $this->ruleForPayment($tenantId, $p),
            'invoice.issued'     => $this->ruleForInvoiceIssued($tenantId, $p),
            'payment.allocated'  => $this->ruleForPaymentAllocated($tenantId, $p),
            'credit_note.issued' => $this->ruleForCreditNoteIssued($tenantId, $p),
            default              => [null, null, []],
        };

        if ($journalCode === null || empty($lines)) {
            return null; // règle inconnue ou montant nul → skipped (tracé)
        }

        $journal = Journal::withoutTenantScope()
            ->where('tenant_id', $tenantId)->where('code', $journalCode)->firstOrFail();

        $settings = AccountingSettings::withoutTenantScope()->where('tenant_id', $tenantId)->firstOrFail();

        $entry = $this->entries->createDraft([
            'journal_id'      => $journal->id,
            'entry_date'      => $date,
            'label'           => $label,
            'currency'        => $currency,
            'lines'           => $lines,
            'source_type'     => $event->source_type,
            'source_id'       => $event->source_id,
            'rule_code'       => $event->event_type,
            'inputs_snapshot' => $p,
        ], $tenantId);

        return $settings->auto_post ? $this->entries->post($entry) : $entry;
    }

    /** @return array{0:?string,1:?string,2:array} [journal, libellé, lignes] */
    private function ruleForPosSale(string $tenantId, array $p): array
    {
        $total = (int) ($p['total'] ?? 0);
        if ($total <= 0) {
            return [null, null, []];
        }

        $lines = [];
        foreach ($p['legs'] ?? [] as $leg) {
            $amount = (int) ($leg['amount'] ?? 0);
            if ($amount <= 0) {
                continue;
            }
            $lines[] = $this->debit($this->treasuryRef($leg['method'] ?? 'cash'), $tenantId, $amount, 'Encaissement ' . ($leg['method'] ?? 'cash'));
        }
        $lines[] = $this->credit('@sales', $tenantId, $total, 'Vente POS');

        return ['VT', 'Vente POS ' . ($p['order_number'] ?? ($p['order_id'] ?? '')), $lines];
    }

    private function ruleForPosRefund(string $tenantId, array $p): array
    {
        $amount = (int) ($p['amount'] ?? 0);
        if ($amount <= 0) {
            return [null, null, []];
        }

        return ['AV', 'Remboursement POS ' . ($p['return_number'] ?? ''), [
            $this->debit('@sales', $tenantId, $amount, 'Annulation de vente'),
            $this->credit($this->treasuryRef($p['method'] ?? 'cash'), $tenantId, $amount, 'Sortie remboursement'),
        ]];
    }

    private function ruleForSessionGap(string $tenantId, array $p): array
    {
        $gap = (int) ($p['difference_cents'] ?? 0);
        if ($gap === 0) {
            return [null, null, []];
        }

        $label = 'Écart de clôture de caisse ' . ($p['session_label'] ?? '');
        // Manquant (gap < 0) : la caisse contient MOINS qu'attendu → charge 658 / crédit caisse.
        // Surplus (gap > 0) : débit caisse / produit 758.
        $lines = $gap < 0
            ? [$this->debit('@cash_short', $tenantId, -$gap, 'Manquant de caisse'), $this->credit('@cash', $tenantId, -$gap, 'Ajustement caisse')]
            : [$this->debit('@cash', $tenantId, $gap, 'Ajustement caisse'), $this->credit('@cash_over', $tenantId, $gap, 'Surplus de caisse')];

        return ['CA', $label, $lines];
    }

    private function ruleForCashMovement(string $tenantId, array $p): array
    {
        $amount = (int) ($p['amount'] ?? 0);
        if ($amount <= 0) {
            return [null, null, []];
        }

        [$debitRef, $creditRef, $label] = match ($p['reason'] ?? '') {
            'float_add'  => ['@cash', '@bank', 'Apport de fond de caisse'],
            'withdrawal' => ['@bank', '@cash', 'Retrait de caisse (dépôt banque)'],
            'expense'    => ['@suspense', '@cash', 'Dépense de caisse (à reclasser)'],
            default      => [null, null, null],  // refund : couvert par pos.refund (pas de doublon)
        };

        if ($debitRef === null) {
            return [null, null, []];
        }

        return ['CA', $label, [
            $this->debit($debitRef, $tenantId, $amount, $label),
            $this->credit($creditRef, $tenantId, $amount, $label),
        ]];
    }

    private function ruleForPayment(string $tenantId, array $p): array
    {
        $amount = (int) ($p['amount'] ?? 0);
        if ($amount <= 0) {
            return [null, null, []];
        }

        $method  = $p['method'] ?? 'cash';
        $journal = $method === 'cash' ? 'CA' : 'BQ';

        return [$journal, 'Encaissement client ' . ($p['order_number'] ?? ''), [
            $this->debit($this->treasuryRef($method), $tenantId, $amount, 'Paiement reçu'),
            $this->credit('@customers', $tenantId, $amount, 'Règlement client'),
        ]];
    }

    /** RC-30 — facture émise : débit 411 client (TTC) / crédit 701 (HT) + 4431 (TVA). */
    private function ruleForInvoiceIssued(string $tenantId, array $p): array
    {
        $total    = (int) ($p['total'] ?? 0);
        $subtotal = (int) ($p['subtotal'] ?? 0);
        $tax      = (int) ($p['tax'] ?? 0);
        if ($total <= 0) {
            return [null, null, []];
        }

        $lines = [
            $this->debit('@customers', $tenantId, $total, 'Facture ' . ($p['invoice_number'] ?? '')),
            $this->credit('@sales', $tenantId, $subtotal, 'Vente (HT)'),
        ];
        if ($tax > 0) {
            $lines[] = $this->credit('@tax_collected', $tenantId, $tax, 'TVA collectée');
        }

        return ['VT', 'Facture ' . ($p['invoice_number'] ?? ''), $lines];
    }

    /** RC-33 — avoir émis : INVERSE de la facture — débit 701 (HT) + 4431 (TVA) / crédit 411 client. */
    private function ruleForCreditNoteIssued(string $tenantId, array $p): array
    {
        $total    = (int) ($p['total'] ?? 0);
        $subtotal = (int) ($p['subtotal'] ?? 0);
        $tax      = (int) ($p['tax'] ?? 0);
        if ($total <= 0) {
            return [null, null, []];
        }

        $ref   = $p['credit_note_number'] ?? '';
        $lines = [$this->debit('@sales', $tenantId, $subtotal, 'Annulation vente (HT)')];
        if ($tax > 0) {
            $lines[] = $this->debit('@tax_collected', $tenantId, $tax, 'TVA à régulariser');
        }
        $lines[] = $this->credit('@customers', $tenantId, $total, 'Avoir ' . $ref);

        return ['AV', 'Avoir ' . $ref, $lines];
    }

    /** RC-30 — paiement alloué à une facture : débit trésorerie / crédit 411 client. */
    private function ruleForPaymentAllocated(string $tenantId, array $p): array
    {
        $amount = (int) ($p['amount'] ?? 0);
        if ($amount <= 0) {
            return [null, null, []];
        }

        $method  = $p['method'] ?? 'cash';
        $journal = $method === 'cash' ? 'CA' : 'BQ';

        return [$journal, 'Règlement facture ' . ($p['invoice_number'] ?? ''), [
            $this->debit($this->treasuryRef($method), $tenantId, $amount, 'Encaissement'),
            $this->credit('@customers', $tenantId, $amount, 'Règlement client'),
        ]];
    }

    // ── Helpers de résolution ──────────────────────────────────────────────────

    private function treasuryRef(string $method): string
    {
        return match ($method) {
            'cash'         => '@cash',
            'mobile_money' => '@mobile_money',
            default        => '@bank',   // card, transfer, cheque
        };
    }

    private function debit(string $ref, string $tenantId, int $amount, ?string $label = null): array
    {
        return ['account_id' => $this->resolveAccount($ref, $tenantId)->id, 'debit_minor' => $amount, 'credit_minor' => 0, 'label' => $label];
    }

    private function credit(string $ref, string $tenantId, int $amount, ?string $label = null): array
    {
        return ['account_id' => $this->resolveAccount($ref, $tenantId)->id, 'debit_minor' => 0, 'credit_minor' => $amount, 'label' => $label];
    }

    /** `@ref` → code (via settings.default_accounts) → compte ACTIF du tenant. */
    private function resolveAccount(string $ref, string $tenantId): Account
    {
        $code = $ref;
        if (str_starts_with($ref, '@')) {
            $settings = AccountingSettings::withoutTenantScope()->where('tenant_id', $tenantId)->firstOrFail();
            $code     = $settings->default_accounts[$ref] ?? null;
            if ($code === null) {
                throw new \DomainException("Référence comptable inconnue : {$ref} (paramètres du tenant).");
            }
        }

        return Account::withoutTenantScope()
            ->where('tenant_id', $tenantId)->where('code', $code)->where('is_active', true)
            ->firstOrFail();
    }
}
