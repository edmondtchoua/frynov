<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Models\CreditNoteApplication;
use App\Modules\Accounting\Models\Invoice;
use App\Modules\Accounting\Models\PaymentAllocation;
use App\Modules\Accounting\Models\Tax;
use App\Modules\Orders\Models\Order;
use App\Modules\Payments\Models\Payment;
use App\Modules\Platform\Services\AuditService;
use App\Shared\Services\SequenceService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * RC-30 — facturation client.
 *
 * `draft` : librement modifiable. À l'ÉMISSION : numéro FA- séquentiel + écriture comptable
 * (411 client / 701 HT + 4431 TVA) via le moteur d'imputation (outbox idempotente) — la facture
 * devient immuable, seule l'allocation de paiements la fait évoluer. Montants en centimes ; TVA en
 * points de base. La génération comptable ne bloque jamais l'émission (best-effort, rattrapable).
 */
class InvoiceService
{
    public function __construct(
        private readonly SequenceService $sequences,
        private readonly ImputationEngine $engine,
        private readonly AuditService $audit,
    ) {}

    /**
     * Crée une facture brouillon avec ses lignes (TVA calculée serveur-side).
     *
     * @param array $data ['customer_id'?,'customer_name'?,'currency'?,'due_date'?,'order_id'?,'notes'?,
     *                      'lines'=>[{label,quantity,unit_price_minor,discount_bp?,tax_id?,product_id?}]]
     */
    public function createDraft(array $data, string $tenantId, ?string $userId = null): Invoice
    {
        $lines = $data['lines'] ?? [];
        if (empty($lines)) {
            throw ValidationException::withMessages(['lines' => ['Une facture comporte au moins une ligne.']]);
        }

        return DB::transaction(function () use ($data, $lines, $tenantId, $userId) {
            $invoice = Invoice::create([
                'tenant_id'         => $tenantId,
                'kind'              => $data['kind'] ?? Invoice::KIND_INVOICE,
                'customer_id'       => $data['customer_id'] ?? null,
                'customer_name'     => $data['customer_name'] ?? null,
                'order_id'          => $data['order_id'] ?? null,
                'proforma_id'       => $data['proforma_id'] ?? null,
                'credit_note_of_id' => $data['credit_note_of_id'] ?? null,
                'currency'          => $data['currency'] ?? 'XOF',
                'due_date'          => $data['due_date'] ?? null,
                'status'            => Invoice::STATUS_DRAFT,
                'notes'             => $data['notes'] ?? null,
                'created_by'        => $userId,
            ]);

            $this->syncLines($invoice, $lines, $tenantId);

            return $invoice->load('lines');
        });
    }

    /** Remplace les lignes d'un brouillon et recalcule les totaux. */
    public function updateDraft(Invoice $invoice, array $lines, string $tenantId): Invoice
    {
        if (! $invoice->isDraft()) {
            throw ValidationException::withMessages(['invoice' => ['Seule une facture brouillon est modifiable.']]);
        }

        return DB::transaction(function () use ($invoice, $lines, $tenantId) {
            $invoice->lines()->delete();
            $this->syncLines($invoice, $lines, $tenantId);

            return $invoice->fresh('lines');
        });
    }

    /** Facture brouillon à partir d'une commande (reprend les lignes au prix de vente). */
    public function fromOrder(Order $order, string $tenantId, ?string $userId = null): Invoice
    {
        $order->loadMissing('lines');
        $lines = $order->lines->map(fn ($l) => [
            'product_id'       => $l->product_id,
            'label'            => $l->name,
            'quantity'         => (int) $l->quantity,
            'unit_price_minor' => (int) $l->unit_price_cents,
            'discount_bp'      => 0,
        ])->all();

        return $this->createDraft([
            'customer_id' => $order->customer_id,
            'order_id'    => $order->id,
            'currency'    => $order->currency,
            'lines'       => $lines,
        ], $tenantId, $userId);
    }

    /** Émet la facture : numéro FA- + écriture d'émission. */
    public function issue(Invoice $invoice, ?string $userId = null): Invoice
    {
        if (! $invoice->isDraft()) {
            throw ValidationException::withMessages(['invoice' => ['Cette facture est déjà émise.']]);
        }
        if ($invoice->total_minor <= 0) {
            throw ValidationException::withMessages(['invoice' => ['Le total de la facture doit être positif.']]);
        }

        $invoice = DB::transaction(function () use ($invoice, $userId) {
            $number = $this->sequences->next($invoice->tenant_id, 'FA', 6);
            $invoice->update([
                'number'     => $number,
                'status'     => Invoice::STATUS_ISSUED,
                'issue_date' => now()->toDateString(),
                'issued_by'  => $userId,
            ]);

            $this->audit->log(
                action: 'accounting.invoice.issued',
                tenantId: $invoice->tenant_id,
                userId: $userId,
                subject: $invoice,
                newValues: ['number' => $number, 'total' => $invoice->total_minor],
            );

            return $invoice;
        });

        // Écriture d'émission (après commit) : 411 client / 701 HT + 4431 TVA.
        $entry = $this->engine->record($invoice->tenant_id, 'invoice.issued', 'Invoice', $invoice->id, [
            'invoice_id'     => $invoice->id,
            'invoice_number' => $invoice->number,
            'total'          => $invoice->total_minor,
            'subtotal'       => $invoice->subtotal_minor,
            'tax'            => $invoice->tax_total_minor,
            'currency'       => $invoice->currency,
            'date'           => $invoice->issue_date->toDateString(),
        ]);
        if ($entry?->entry_id) {
            $invoice->update(['entry_id' => $entry->entry_id]);
        }

        return $invoice->fresh('lines');
    }

    /**
     * Alloue un paiement à une facture émise. Met à jour le solde + statut, génère l'écriture
     * d'encaissement (trésorerie / 411). L'allocation est bornée au reste dû de la facture ET au
     * disponible du paiement (non déjà alloué).
     */
    public function allocatePayment(Payment $payment, Invoice $invoice, int $amountMinor, ?string $userId = null): PaymentAllocation
    {
        if (in_array($invoice->status, [Invoice::STATUS_DRAFT, Invoice::STATUS_CANCELLED], true)) {
            throw ValidationException::withMessages(['invoice' => ['La facture doit être émise pour recevoir un paiement.']]);
        }
        if ($amountMinor <= 0) {
            throw ValidationException::withMessages(['amount_minor' => ['Le montant doit être strictement positif.']]);
        }

        $alreadyAllocated = (int) PaymentAllocation::withoutTenantScope()
            ->where('payment_id', $payment->id)->sum('amount_minor');
        $paymentAvailable = max(0, (int) $payment->amount_cents - $alreadyAllocated);

        $max = min($invoice->remainingMinor(), $paymentAvailable);
        if ($amountMinor > $max) {
            throw ValidationException::withMessages([
                'amount_minor' => ["Allocation ({$amountMinor}) supérieure au disponible ({$max})."],
            ]);
        }

        $allocation = DB::transaction(function () use ($payment, $invoice, $amountMinor, $userId) {
            $alloc = PaymentAllocation::create([
                'tenant_id'    => $invoice->tenant_id,
                'payment_id'   => $payment->id,
                'invoice_id'   => $invoice->id,
                'amount_minor' => $amountMinor,
                'created_by'   => $userId,
            ]);

            $invoice->paid_minor = (int) $invoice->paid_minor + $amountMinor;
            $invoice->status     = $this->settlementStatus($invoice);
            $invoice->save();

            return $alloc;
        });

        $entry = $this->engine->record($invoice->tenant_id, 'payment.allocated', 'PaymentAllocation', $allocation->id, [
            'allocation_id'  => $allocation->id,
            'invoice_number' => $invoice->number,
            'amount'         => $amountMinor,
            'method'         => $payment->method,
            'currency'       => $invoice->currency,
            'date'           => now()->toDateString(),
        ]);
        if ($entry?->entry_id) {
            $allocation->update(['entry_id' => $entry->entry_id]);
        }

        return $allocation;
    }

    // ── Avoirs (notes de crédit) — RC-33 ────────────────────────────────────────

    /**
     * Crée un avoir brouillon rattaché à une facture émise (reprend ses lignes par défaut, ou un
     * sous-ensemble fourni pour un avoir partiel).
     */
    public function createCreditNoteFromInvoice(Invoice $invoice, ?array $lines = null, ?string $userId = null): Invoice
    {
        if ($invoice->isCreditNote()) {
            throw ValidationException::withMessages(['invoice' => ['Un avoir ne peut pas porter sur un autre avoir.']]);
        }
        if (in_array($invoice->status, [Invoice::STATUS_DRAFT, Invoice::STATUS_CANCELLED], true)) {
            throw ValidationException::withMessages(['invoice' => ['La facture doit être émise pour recevoir un avoir.']]);
        }

        $invoice->loadMissing('lines');
        $lines ??= $invoice->lines->map(fn ($l) => [
            'product_id'       => $l->product_id,
            'label'            => $l->label,
            'quantity'         => (int) $l->quantity,
            'unit_price_minor' => (int) $l->unit_price_minor,
            'discount_bp'      => (int) $l->discount_bp,
            'tax_id'           => $l->tax_id,
        ])->all();

        return $this->createDraft([
            'kind'              => Invoice::KIND_CREDIT_NOTE,
            'credit_note_of_id' => $invoice->id,
            'customer_id'       => $invoice->customer_id,
            'customer_name'     => $invoice->customer_name,
            'currency'          => $invoice->currency,
            'lines'             => $lines,
        ], $invoice->tenant_id, $userId);
    }

    /** Émet un avoir : numéro AV- + écriture INVERSE (701/4431 débit, 411 crédit, journal AV). */
    public function issueCreditNote(Invoice $creditNote, ?string $userId = null): Invoice
    {
        if (! $creditNote->isCreditNote()) {
            throw ValidationException::withMessages(['credit_note' => ['Ce document n\'est pas un avoir.']]);
        }
        if (! $creditNote->isDraft()) {
            throw ValidationException::withMessages(['credit_note' => ['Cet avoir est déjà émis.']]);
        }
        if ($creditNote->total_minor <= 0) {
            throw ValidationException::withMessages(['credit_note' => ['Le total de l\'avoir doit être positif.']]);
        }

        $creditNote = DB::transaction(function () use ($creditNote, $userId) {
            $number = $this->sequences->next($creditNote->tenant_id, 'AV', 6);
            $creditNote->update([
                'number'     => $number,
                'status'     => Invoice::STATUS_ISSUED,
                'issue_date' => now()->toDateString(),
                'issued_by'  => $userId,
            ]);

            $this->audit->log(
                action: 'accounting.credit_note.issued',
                tenantId: $creditNote->tenant_id,
                userId: $userId,
                subject: $creditNote,
                newValues: ['number' => $number, 'total' => $creditNote->total_minor, 'invoice_id' => $creditNote->credit_note_of_id],
            );

            return $creditNote;
        });

        $entry = $this->engine->record($creditNote->tenant_id, 'credit_note.issued', 'Invoice', $creditNote->id, [
            'credit_note_id'     => $creditNote->id,
            'credit_note_number' => $creditNote->number,
            'total'              => $creditNote->total_minor,
            'subtotal'           => $creditNote->subtotal_minor,
            'tax'                => $creditNote->tax_total_minor,
            'currency'           => $creditNote->currency,
            'date'               => $creditNote->issue_date->toDateString(),
        ]);
        if ($entry?->entry_id) {
            $creditNote->update(['entry_id' => $entry->entry_id]);
        }

        return $creditNote->fresh('lines');
    }

    /**
     * Applique un avoir émis à une facture émise : réduit le reste dû de la facture. Borné au reste
     * applicable de l'avoir ET au reste dû de la facture. Aucune écriture supplémentaire (les deux
     * mouvements sur 411 sont déjà comptabilisés à l'émission) : c'est un lettrage/rapprochement.
     */
    public function applyCreditNote(Invoice $creditNote, Invoice $invoice, int $amountMinor, ?string $userId = null): CreditNoteApplication
    {
        if (! $creditNote->isCreditNote() || $invoice->isCreditNote()) {
            throw ValidationException::withMessages(['credit_note' => ['Application invalide : avoir → facture attendue.']]);
        }
        if ($creditNote->status === Invoice::STATUS_DRAFT || $invoice->status === Invoice::STATUS_DRAFT) {
            throw ValidationException::withMessages(['credit_note' => ['L\'avoir et la facture doivent être émis.']]);
        }
        if ($creditNote->customer_id && $invoice->customer_id && $creditNote->customer_id !== $invoice->customer_id) {
            throw ValidationException::withMessages(['invoice' => ['L\'avoir et la facture doivent concerner le même client.']]);
        }
        if ($amountMinor <= 0) {
            throw ValidationException::withMessages(['amount_minor' => ['Le montant doit être strictement positif.']]);
        }

        $max = min($creditNote->remainingMinor(), $invoice->remainingMinor());
        if ($amountMinor > $max) {
            throw ValidationException::withMessages([
                'amount_minor' => ["Application ({$amountMinor}) supérieure au disponible ({$max})."],
            ]);
        }

        return DB::transaction(function () use ($creditNote, $invoice, $amountMinor, $userId) {
            // Une seule ligne par (avoir, facture) : les applications successives s'accumulent.
            $application = CreditNoteApplication::withoutTenantScope()
                ->where('credit_note_id', $creditNote->id)
                ->where('invoice_id', $invoice->id)
                ->first();

            if ($application) {
                $application->amount_minor = (int) $application->amount_minor + $amountMinor;
                $application->created_by   = $userId;
                $application->save();
            } else {
                $application = CreditNoteApplication::create([
                    'tenant_id'      => $invoice->tenant_id,
                    'credit_note_id' => $creditNote->id,
                    'invoice_id'     => $invoice->id,
                    'amount_minor'   => $amountMinor,
                    'created_by'     => $userId,
                ]);
            }

            // L'avoir suit son "appliqué" via paid_minor ; la facture via credited_minor.
            $creditNote->paid_minor = (int) $creditNote->paid_minor + $amountMinor;
            $creditNote->status     = $this->settlementStatus($creditNote);
            $creditNote->save();

            $invoice->credited_minor = (int) $invoice->credited_minor + $amountMinor;
            $invoice->status         = $this->settlementStatus($invoice);
            $invoice->save();

            $this->audit->log(
                action: 'accounting.credit_note.applied',
                tenantId: $invoice->tenant_id,
                userId: $userId,
                subject: $application,
                newValues: ['credit_note' => $creditNote->number, 'invoice' => $invoice->number, 'amount' => $amountMinor],
            );

            return $application;
        });
    }

    // ── Interne ────────────────────────────────────────────────────────────────

    /** Statut de règlement d'après le total réglé (paiements + avoirs) vs le total du document. */
    private function settlementStatus(Invoice $doc): string
    {
        $settled = (int) $doc->paid_minor + (int) $doc->credited_minor;

        return $settled >= (int) $doc->total_minor ? Invoice::STATUS_PAID : Invoice::STATUS_PARTIALLY_PAID;
    }

    private function syncLines(Invoice $invoice, array $lines, string $tenantId): void
    {
        $taxes    = Tax::withoutTenantScope()->where('tenant_id', $tenantId)->get()->keyBy('id');
        $subtotal = 0;
        $taxTotal = 0;

        foreach ($lines as $l) {
            $qty      = max(1, (int) ($l['quantity'] ?? 1));
            $unit     = max(0, (int) ($l['unit_price_minor'] ?? 0));
            $discBp   = min(10000, max(0, (int) ($l['discount_bp'] ?? 0)));
            $base     = $qty * $unit;
            $discount = intdiv($base * $discBp + 5000, 10000);
            $lineHt   = $base - $discount;

            $tax      = ! empty($l['tax_id']) ? ($taxes[$l['tax_id']] ?? null) : null;
            $lineTax  = ($tax && $tax->is_active) ? $tax->amountFor($lineHt) : 0;

            $invoice->lines()->create([
                'tenant_id'        => $tenantId,
                'product_id'       => $l['product_id'] ?? null,
                'label'            => $l['label'],
                'quantity'         => $qty,
                'unit_price_minor' => $unit,
                'discount_bp'      => $discBp,
                'tax_id'           => $tax?->id,
                'subtotal_minor'   => $lineHt,
                'tax_minor'        => $lineTax,
                'total_minor'      => $lineHt + $lineTax,
            ]);

            $subtotal += $lineHt;
            $taxTotal += $lineTax;
        }

        $invoice->update([
            'subtotal_minor'  => $subtotal,
            'tax_total_minor' => $taxTotal,
            'total_minor'     => $subtotal + $taxTotal,
        ]);
    }
}
