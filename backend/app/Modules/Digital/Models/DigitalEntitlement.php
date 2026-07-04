<?php

namespace App\Modules\Digital\Models;

use App\Modules\Catalog\Models\Product;
use App\Shared\Traits\HasTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * RC-5E — droit d'accès digital accordé à un client après la vente (téléchargement / licence).
 */
class DigitalEntitlement extends Model
{
    use HasTenant, HasUuids, SoftDeletes;

    public const STATUS_ACTIVE  = 'active';
    public const STATUS_REVOKED = 'revoked';
    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'tenant_id', 'product_id', 'variant_id', 'order_id', 'order_line_id', 'unit_index', 'customer_id',
        'fulfillment_type', 'access_token', 'license_key',
        'status', 'granted_at', 'expires_at', 'revoked_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'unit_index' => 'integer',
            'granted_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /** Accès en cours : actif, non révoqué et non expiré. */
    public function isAccessible(): bool
    {
        return $this->status === self::STATUS_ACTIVE
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Payload API. La clé de licence n'est exposée qu'au propriétaire de l'accès (vue détaillée),
     * jamais dans les listes scopées commande — d'où le paramètre explicite.
     */
    public function toApiArray(bool $withSecret = false): array
    {
        return array_merge([
            'id'               => $this->id,
            'product_id'       => $this->product_id,
            'variant_id'       => $this->variant_id,
            'order_id'         => $this->order_id,
            'order_line_id'    => $this->order_line_id,
            'unit_index'       => $this->unit_index,
            'customer_id'      => $this->customer_id,
            'fulfillment_type' => $this->fulfillment_type,
            'status'           => $this->status,
            'granted_at'       => $this->granted_at?->toISOString(),
            'expires_at'       => $this->expires_at?->toISOString(),
        ], $withSecret ? [
            'access_token' => $this->access_token,
            'license_key'  => $this->license_key,
        ] : []);
    }
}
