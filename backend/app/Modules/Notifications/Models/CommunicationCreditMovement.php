<?php

namespace App\Modules\Notifications\Models;

use App\Shared\Traits\HasTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * RC-7E — mouvement de crédits de communication (journal append-only, traçabilité comptable).
 */
class CommunicationCreditMovement extends Model
{
    use HasTenant, HasUuids;

    public const REASON_RECHARGE   = 'recharge';   // achat d'un pack (rail de paiement manuel)
    public const REASON_SEND       = 'send';       // décompte à l'envoi d'une notification
    public const REASON_REFUND     = 'refund';     // remboursement (échec d'envoi après débit)
    public const REASON_ADJUSTMENT = 'adjustment'; // ajustement manuel (support)

    protected $table = 'communication_credit_movements';

    protected $fillable = [
        'tenant_id', 'channel', 'delta', 'balance_after', 'reason', 'reference', 'meta', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'delta'         => 'integer',
            'balance_after' => 'integer',
            'meta'          => 'array',
        ];
    }

    public function toApiArray(): array
    {
        return [
            'id'            => $this->id,
            'channel'       => $this->channel,
            'delta'         => $this->delta,
            'balance_after' => $this->balance_after,
            'reason'        => $this->reason,
            'reference'     => $this->reference,
            'meta'          => $this->meta,
            'created_at'    => $this->created_at?->toISOString(),
        ];
    }
}
