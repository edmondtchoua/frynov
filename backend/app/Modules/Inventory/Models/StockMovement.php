<?php

namespace App\Modules\Inventory\Models;

use App\Modules\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    use HasUuids;

    const TYPE_IN         = 'in';
    const TYPE_OUT        = 'out';
    const TYPE_ADJUSTMENT = 'adjustment';
    const TYPE_RETURN     = 'return';

    const REASON_DELIVERY = 'delivery';
    const REASON_SALE     = 'sale';
    const REASON_RETURN   = 'return';
    const REASON_LOSS     = 'loss';
    const REASON_COUNT    = 'count';
    const REASON_MANUAL   = 'manual';

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
    ];

    protected function casts(): array
    {
        return [
            'quantity'        => 'integer',
            'quantity_before' => 'integer',
            'quantity_after'  => 'integer',
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
