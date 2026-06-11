<?php

namespace App\Modules\Warranties\Models;

use App\Modules\Catalog\Models\Product;
use App\Shared\Traits\HasTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * RC-5D — contrat de garantie effectif, généré à la vente. Lie la garantie au client, à la ligne de
 * commande et — pour le sérialisé — à l'unité vendue (IMEI/VIN).
 */
class WarrantyContract extends Model
{
    use HasTenant, HasUuids, SoftDeletes;

    public const STATUS_ACTIVE  = 'active';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_VOID    = 'void';

    protected $fillable = [
        'tenant_id', 'warranty_policy_id', 'product_id', 'variant_id', 'inventory_unit_id',
        'order_id', 'order_line_id', 'customer_id', 'serial_value',
        'starts_at', 'ends_at', 'status', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at'   => 'datetime',
        ];
    }

    /** Couverture en cours : statut actif ET date de fin non dépassée. */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE
            && $this->ends_at !== null
            && $this->ends_at->isFuture();
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(WarrantyPolicy::class, 'warranty_policy_id');
    }

    public function toApiArray(): array
    {
        return [
            'id'                 => $this->id,
            'warranty_policy_id' => $this->warranty_policy_id,
            'product_id'         => $this->product_id,
            'variant_id'         => $this->variant_id,
            'inventory_unit_id'  => $this->inventory_unit_id,
            'order_id'           => $this->order_id,
            'order_line_id'      => $this->order_line_id,
            'customer_id'        => $this->customer_id,
            'serial_value'       => $this->serial_value,
            'starts_at'          => $this->starts_at?->toISOString(),
            'ends_at'            => $this->ends_at?->toISOString(),
            'status'             => $this->status,
        ];
    }
}
