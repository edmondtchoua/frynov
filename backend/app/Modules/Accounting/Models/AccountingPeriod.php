<?php

namespace App\Modules\Accounting\Models;

use App\Shared\Traits\HasTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * RC-23 — période comptable (mensuelle). `locked` interdit toute nouvelle écriture datée dans la
 * période (le déverrouillage exige la permission dédiée + motif — réouverture contrôlée, P4).
 */
class AccountingPeriod extends Model
{
    use HasTenant, HasUuids;

    public const STATUS_OPEN   = 'open';
    public const STATUS_LOCKED = 'locked';
    public const STATUS_CLOSED = 'closed';

    protected $table = 'accounting_periods';

    protected $fillable = [
        'tenant_id', 'fiscal_year_id', 'label', 'starts_on', 'ends_on',
        'status', 'locked_by', 'locked_at', 'lock_reason',
    ];

    protected function casts(): array
    {
        return [
            // Dates PURES sérialisées 'Y-m-d' (sans heure UTC) : évite le décalage d'un jour
            // à l'affichage dans les navigateurs à l'ouest d'UTC.
            'starts_on' => 'date:Y-m-d',
            'ends_on'   => 'date:Y-m-d',
            'locked_at' => 'datetime',
        ];
    }

    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class, 'fiscal_year_id');
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }
}
