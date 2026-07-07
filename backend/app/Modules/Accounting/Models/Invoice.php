<?php

namespace App\Modules\Accounting\Models;

use App\Shared\Traits\HasTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * RC-30 — facture client. `draft` : modifiable. `issued` : numérotée, écriture générée, immuable
 * (seule l'allocation de paiements la fait évoluer partially_paid → paid). Montants en centimes.
 *
 * RC-33 — sert aussi d'AVOIR (kind = credit_note, numéro AV-) : écriture inverse à l'émission, puis
 * `paid_minor` = montant appliqué à des factures. `remainingMinor()` = reste à régler/appliquer.
 */
class Invoice extends Model
{
    use HasTenant, HasUuids;

    public const STATUS_DRAFT          = 'draft';
    public const STATUS_ISSUED         = 'issued';
    public const STATUS_PARTIALLY_PAID = 'partially_paid';
    public const STATUS_PAID           = 'paid';
    public const STATUS_CANCELLED      = 'cancelled';

    public const KIND_INVOICE     = 'invoice';
    public const KIND_CREDIT_NOTE = 'credit_note';

    protected $table = 'invoices';

    protected $fillable = [
        'tenant_id', 'number', 'kind', 'customer_id', 'order_id', 'proforma_id', 'credit_note_of_id',
        'currency', 'issue_date', 'due_date', 'status',
        'subtotal_minor', 'tax_total_minor', 'total_minor', 'paid_minor', 'credited_minor',
        'entry_id', 'customer_name', 'notes', 'created_by', 'issued_by',
    ];

    protected function casts(): array
    {
        return [
            'issue_date'      => 'date:Y-m-d',
            'due_date'        => 'date:Y-m-d',
            'subtotal_minor'  => 'integer',
            'tax_total_minor' => 'integer',
            'total_minor'     => 'integer',
            'paid_minor'      => 'integer',
            'credited_minor'  => 'integer',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class, 'invoice_id');
    }

    /** Facture d'origine d'un avoir (kind=credit_note). */
    public function creditNoteOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'credit_note_of_id');
    }

    /** Avoirs émis contre cette facture. */
    public function creditNotes(): HasMany
    {
        return $this->hasMany(self::class, 'credit_note_of_id');
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isCreditNote(): bool
    {
        return $this->kind === self::KIND_CREDIT_NOTE;
    }

    /**
     * Reste à régler (facture) ou à appliquer (avoir). Pour une facture, on retranche les paiements
     * ET les avoirs appliqués ; pour un avoir, `paid_minor` = déjà appliqué (credited_minor = 0).
     */
    public function remainingMinor(): int
    {
        return max(0, $this->total_minor - $this->paid_minor - $this->credited_minor);
    }
}
