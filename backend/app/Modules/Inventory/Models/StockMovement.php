<?php

namespace App\Modules\Inventory\Models;

use App\Modules\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Shared\Traits\HasTenant;

class StockMovement extends Model
{
    use HasTenant, HasUuids;

    const TYPE_IN         = 'in';
    const TYPE_OUT        = 'out';
    const TYPE_ADJUSTMENT = 'adjustment';
    const TYPE_RETURN     = 'return';

    const REASON_DELIVERY  = 'delivery';
    const REASON_SALE      = 'sale';
    const REASON_RETURN    = 'return';
    const REASON_LOSS      = 'loss';
    const REASON_COUNT     = 'count';
    const REASON_MANUAL    = 'manual';
    const REASON_TRANSFER  = 'transfer'; // inter-warehouse transfer (Axe 2)

    protected $fillable = [
        'tenant_id',
        'stock_id',
        'product_id',
        'variant_id',
        'type',
        'quantity',
        'quantity_before',
        'quantity_after',
        'reason',
        'reference',
        'note',
        'performed_by',
        'unit_cost_cents_snapshot', // Axe 1 — CMUP at time of movement
        'cmup_deferred',            // Axe 1 — async recalculation flag
    ];

    protected function casts(): array
    {
        return [
            'quantity'                 => 'integer',
            'quantity_before'          => 'integer',
            'quantity_after'           => 'integer',
            'unit_cost_cents_snapshot' => 'integer',
            'cmup_deferred'            => 'boolean',
        ];
    }

    public function stock(): BelongsTo
    {
        return $this->belongsTo(Stock::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
