<?php

namespace App\Modules\Accounting\Models;

use App\Shared\Traits\HasTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** RC-25 — ligne d'écriture : exactement UN côté (débit ou crédit) strictement positif. */
class EntryLine extends Model
{
    use HasTenant, HasUuids;

    protected $table = 'accounting_entry_lines';

    protected $fillable = [
        'tenant_id', 'entry_id', 'account_id', 'label',
        'debit_minor', 'credit_minor', 'tax_id',
        'third_party_type', 'third_party_id',
        'lettrage_code', 'lettered_at',
    ];

    protected function casts(): array
    {
        return ['debit_minor' => 'integer', 'credit_minor' => 'integer', 'lettered_at' => 'datetime'];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }
}
