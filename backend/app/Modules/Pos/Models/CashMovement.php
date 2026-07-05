<?php

namespace App\Modules\Pos\Models;

use App\Shared\Traits\HasTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A cash-drawer movement inside a session (mouvement de caisse).
 *
 * Direction 'in' adds cash to the drawer (float top-up, change), 'out' removes it
 * (withdrawal, petty expense, cash refund). Amounts are always positive integer
 * centimes; the direction carries the sign for reconciliation.
 */
class CashMovement extends Model
{
    use HasTenant, HasUuids;

    public const DIRECTION_IN  = 'in';
    public const DIRECTION_OUT = 'out';

    /** Canonical reasons (free-form allowed, but these drive the UI presets). */
    public const REASON_FLOAT_ADD  = 'float_add';   // approvisionnement / ajout de fond
    public const REASON_WITHDRAWAL = 'withdrawal';  // prélèvement / dépôt banque
    public const REASON_EXPENSE    = 'expense';     // dépense payée depuis la caisse
    public const REASON_REFUND     = 'refund';      // remboursement espèces (leg d'un retour)

    protected $fillable = [
        'tenant_id',
        'session_id',
        'direction',
        'amount_cents',
        'reason',
        'note',
        'order_id',
        'performed_by',
    ];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
        ];
    }

    /** Signed value for the drawer: +amount for a pay-in, −amount for a pay-out. */
    public function signedAmount(): int
    {
        return $this->direction === self::DIRECTION_IN
            ? $this->amount_cents
            : -$this->amount_cents;
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(CashRegisterSession::class, 'session_id');
    }
}
