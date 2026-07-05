<?php

namespace App\Modules\Accounting\Services;

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
                'tenant_id'     => $tenantId,
                'kind'          => $data['kind'] ?? 'invoice',
                'customer_id'   => $data['customer_id'] ?? null,
                'customer_name' => $data['customer_name'] ?? null,
                'order_id'      => $data['order_id'] ?? null,
                'currency'      => $data['currency'] ?? 'XOF',
                'due_date'      => $data['due_date'] ?? null,
                'status'        => Invoice::STATUS_DRAFT,
                'notes'         => $data['notes'] ?? null,
                'created_by'    => $userId,
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

            $paid = (int) $invoice->paid_minor + $amountMinor;
            $invoice->update([
                'paid_minor' => $paid,
                'status'     => $paid >= $invoice->total_minor ? Invoice::STATUS_PAID : Invoice::STATUS_PARTIALLY_PAID,
            ]);

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

    // ── Interne ────────────────────────────────────────────────────────────────

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
