<?php

namespace App\Modules\Orders\Models;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderReturnLine extends Model
{
    use HasUuids;

    protected $fillable = [
        'return_id', 'order_line_id', 'product_id', 'variant_id',
        'quantity_requested', 'quantity_approved', 'quantity_restocked',
        'condition', 'reason', 'unit_price_cents',
    ];

    protected function casts(): array
    {
        return [
            'quantity_requested' => 'integer',
            'quantity_approved'  => 'integer',
            'quantity_restocked' => 'integer',
            'unit_price_cents'   => 'integer',
        ];
    }

    public function orderReturn(): BelongsTo { return $this->belongsTo(OrderReturn::class, 'return_id'); }
    public function product(): BelongsTo     { return $this->belongsTo(Product::class); }
    public function variant(): BelongsTo     { return $this->belongsTo(ProductVariant::class); }
    public function orderLine(): BelongsTo   { return $this->belongsTo(OrderLine::class, 'order_line_id'); }

    public function isResalable(): bool { return $this->condition === 'resalable'; }
}
