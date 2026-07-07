<?php

namespace App\Modules\Accounting\Models;

use App\Shared\Traits\HasTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** RC-30 — allocation d'un paiement à une facture (N↔N : partiel, multiple, trop-perçu). */
class PaymentAllocation extends Model
{
    use HasTenant, HasUuids;

    protected $table = 'payment_allocations';

    protected $fillable = [
        'tenant_id', 'payment_id', 'invoice_id', 'amount_minor', 'entry_id', 'created_by',
    ];

    protected function casts(): array
    {
        return ['amount_minor' => 'integer'];
    }
}
