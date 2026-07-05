<?php

namespace App\Modules\Inventory\Models;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Shared\Traits\HasTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * RC-5B — unité physique sérialisée (IMEI/VIN/numéro de série…). Source de vérité du stock pour les
 * produits `stock_tracking=serialized`.
 */
class InventoryUnit extends Model
{
    use HasTenant, HasUuids, SoftDeletes;

    // Types d'identifiant connus (extensibles : serial_type est un string libre).
    public const TYPE_IMEI   = 'imei';
    public const TYPE_VIN    = 'vin';
    public const TYPE_SERIAL = 'serial';
    public const TYPE_CUSTOM = 'custom';

    public const CONDITION_NEW        = 'new';
    public const CONDITION_USED       = 'used';
    public const CONDITION_REFURBISHED = 'refurbished';
    public const CONDITION_DAMAGED    = 'damaged';

    public const STATUS_IN_STOCK   = 'in_stock';
    public const STATUS_RESERVED   = 'reserved';
    public const STATUS_SOLD       = 'sold';
    public const STATUS_RETURNED   = 'returned';
    public const STATUS_REPAIR     = 'repair';
    public const STATUS_QUARANTINE = 'quarantine';
    public const STATUS_LOST       = 'lost';
    public const STATUS_SCRAPPED   = 'scrapped';

    protected $fillable = [
        'tenant_id', 'product_id', 'variant_id', 'warehouse_id',
        'serial_type', 'serial_value', 'normalized_serial',
        'condition', 'status',
        'received_at', 'sold_at',
        'order_id', 'order_line_id', 'customer_id',
        'warranty_started_at', 'warranty_ends_at',
        'notes', 'received_by',
    ];

    protected function casts(): array
    {
        return [
            'received_at'         => 'datetime',
            'sold_at'             => 'datetime',
            'warranty_started_at' => 'datetime',
            'warranty_ends_at'    => 'datetime',
        ];
    }

    public function isAvailable(): bool
    {
        return $this->status === self::STATUS_IN_STOCK;
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /** Payload API — l'identifiant brut reste visible pour l'opérateur (scan/recherche). */
    public function toApiArray(): array
    {
        return [
            'id'            => $this->id,
            'product_id'    => $this->product_id,
            'variant_id'    => $this->variant_id,
            'warehouse_id'  => $this->warehouse_id,
            'serial_type'   => $this->serial_type,
            'serial_value'  => $this->serial_value,
            'condition'     => $this->condition,
            'status'        => $this->status,
            'received_at'   => $this->received_at?->toISOString(),
            'sold_at'       => $this->sold_at?->toISOString(),
            'created_at'    => $this->created_at?->toISOString(),
        ];
    }
}
