<?php

namespace App\Modules\Accounting\Models;

use App\Shared\Traits\HasTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** RC-33 — application d'un avoir à une facture (réduit le reste dû ; borné aux deux documents). */
class CreditNoteApplication extends Model
{
    use HasTenant, HasUuids;

    protected $table = 'credit_note_applications';

    protected $fillable = [
        'tenant_id', 'credit_note_id', 'invoice_id', 'amount_minor', 'created_by',
    ];

    protected function casts(): array
    {
        return ['amount_minor' => 'integer'];
    }
}
