<?php

namespace App\Modules\Billing\Models;

use App\Shared\Traits\HasTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * RC-6G — mouvement d'avoir (ledger signé) : +avoir (trop-perçu, proration) / -consommation.
 */
class TenantCredit extends Model
{
    use HasTenant, HasUuids;

    public const SOURCE_OVERPAID    = 'overpaid';
    public const SOURCE_PRORATION   = 'proration';
    public const SOURCE_CONSUMPTION = 'consumption';
    public const SOURCE_ADJUSTMENT  = 'adjustment';

    protected $table = 'tenant_credits';

    protected $fillable = [
        'tenant_id', 'currency', 'amount_minor', 'source', 'reference', 'created_by',
    ];

    protected function casts(): array
    {
        return ['amount_minor' => 'integer'];
    }
}
