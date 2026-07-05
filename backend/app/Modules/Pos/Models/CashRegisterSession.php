<?php

namespace App\Modules\Pos\Models;

use App\Shared\Traits\HasTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A cash-register session (session de caisse).
 *
 * A cashier OPENS a session with an opening float, rings up POS sales during it,
 * then CLOSES it by counting the drawer. The system computes expected vs counted
 * cash and the signed difference (écart) for reconciliation.
 *
 * All amounts are integer centimes (×100), per the project-wide money convention.
 */
class CashRegisterSession extends Model
{
    use HasTenant, HasUuids;

    public const STATUS_OPEN   = 'open';
    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'tenant_id',
        'warehouse_id',
        'label',
        'status',
        'opening_float_cents',
        'total_sales_cents',
        'cash_sales_cents',
        'sales_count',
        'expected_cash_cents',
        'counted_cash_cents',
        'difference_cents',
        'opened_by',
        'closed_by',
        'opened_at',
        'closed_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'opening_float_cents' => 'integer',
            'total_sales_cents'   => 'integer',
            'cash_sales_cents'    => 'integer',
            'sales_count'         => 'integer',
            'expected_cash_cents' => 'integer',
            'counted_cash_cents'  => 'integer',
            'difference_cents'    => 'integer',
            'opened_at'           => 'datetime',
            'closed_at'           => 'datetime',
        ];
    }

    // ── State ────────────────────────────────────────────────────────────────

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    /** Expected cash in the drawer right now = opening float + cash sales. */
    public function expectedCashNow(): int
    {
        return $this->opening_float_cents + $this->cash_sales_cents;
    }

    // ── Relations ────────────────────────────────────────────────────────────

    public function orders(): HasMany
    {
        return $this->hasMany(\App\Modules\Orders\Models\Order::class);
    }

    public function opener(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'opened_by');
    }
}
