<?php

namespace App\Modules\Notifications\Models;

use App\Shared\Traits\HasTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * RC-7F — commande de recharge payable par Mobile Money (référence unique → webhook → crédit auto).
 */
class CreditRechargeOrder extends Model
{
    use HasTenant, HasUuids;

    public const STATUS_PENDING      = 'pending';
    public const STATUS_PAID         = 'paid';
    public const STATUS_CANCELLED    = 'cancelled';
    public const STATUS_NEEDS_REVIEW = 'needs_review'; // paiement reçu mais divergent (montant/devise)

    protected $table = 'credit_recharge_orders';

    protected $fillable = [
        'tenant_id', 'reference', 'pack_code', 'channel', 'credits', 'price_cents', 'currency',
        'status', 'provider', 'provider_ref', 'paid_at', 'movement_id', 'meta', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'credits'     => 'integer',
            'price_cents' => 'integer',
            'paid_at'     => 'datetime',
            'meta'        => 'array',
        ];
    }

    public function toApiArray(): array
    {
        return [
            'id'           => $this->id,
            'reference'    => $this->reference,
            'pack_code'    => $this->pack_code,
            'channel'      => $this->channel,
            'credits'      => $this->credits,
            'price_cents'  => $this->price_cents,
            'currency'     => $this->currency,
            'status'       => $this->status,
            'provider'     => $this->provider,
            'provider_ref' => $this->provider_ref,
            'paid_at'      => $this->paid_at?->toISOString(),
            'created_at'   => $this->created_at?->toISOString(),
        ];
    }
}
