<?php

namespace App\Modules\Accounting\Models;

use App\Shared\Traits\HasTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** RC-23 — exercice comptable d'un tenant. */
class FiscalYear extends Model
{
    use HasTenant, HasUuids;

    public const STATUS_OPEN    = 'open';
    public const STATUS_CLOSING = 'closing';
    public const STATUS_CLOSED  = 'closed';

    protected $table = 'accounting_fiscal_years';

    protected $fillable = [
        'tenant_id', 'label', 'starts_on', 'ends_on', 'status',
        'closed_by', 'closed_at', 'carry_forward_entry_id',
    ];

    protected function casts(): array
    {
        return [
            // Dates PURES sérialisées 'Y-m-d' (cf. AccountingPeriod — pas de décalage UTC à l'affichage).
            'starts_on' => 'date:Y-m-d',
            'ends_on'   => 'date:Y-m-d',
            'closed_at' => 'datetime',
        ];
    }

    public function periods(): HasMany
    {
        return $this->hasMany(AccountingPeriod::class, 'fiscal_year_id')->orderBy('starts_on');
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }
}
