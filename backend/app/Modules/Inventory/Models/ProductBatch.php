<?php

namespace App\Modules\Inventory\Models;

use App\Shared\Traits\HasTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * RC-6H — lot de produit (DLUO/DLC) : réception par lot, consommation FEFO à la vente.
 */
class ProductBatch extends Model
{
    use HasTenant, HasUuids;

    public const STATUS_ACTIVE     = 'active';
    public const STATUS_QUARANTINE = 'quarantine';
    public const STATUS_EXPIRED    = 'expired';
    public const STATUS_EXHAUSTED  = 'exhausted';
    public const STATUS_RECALLED   = 'recalled';

    protected $table = 'product_batches';

    protected $fillable = [
        'tenant_id', 'product_id', 'variant_id',
        'batch_number', 'serial_number', 'manufacturing_date', 'expiry_date',
        'quantity_initial', 'quantity', 'status', 'notes', 'received_by',
    ];

    protected function casts(): array
    {
        return [
            'manufacturing_date' => 'date',
            'expiry_date'        => 'date',
            'quantity_initial'   => 'integer',
            'quantity'           => 'integer',
        ];
    }

    public function toApiArray(): array
    {
        return [
            'id'               => $this->id,
            'product_id'       => $this->product_id,
            'variant_id'       => $this->variant_id,
            'batch_number'     => $this->batch_number,
            'expiry_date'      => $this->expiry_date?->toDateString(),
            'quantity_initial' => $this->quantity_initial,
            'quantity'         => $this->quantity,
            'status'           => $this->status,
            'created_at'       => $this->created_at?->toISOString(),
        ];
    }
}
