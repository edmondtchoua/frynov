<?php

namespace App\Modules\Orders\Models;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Shared\Traits\HasTenant;

class OrderLine extends Model
{
    use HasTenant, HasUuids;

    protected $fillable = [
        'order_id',
        'tenant_id',
        'product_id',
        'variant_id',
        'sku',
        'name',
        'quantity',
        'unit_price_cents',
    ];

    protected function casts(): array
    {
        return [
            'quantity'         => 'integer',
            'unit_price_cents' => 'integer',
        ];
    }

    public function lineTotalCents(): int
    {
        return $this->quantity * $this->unit_price_cents;
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }
}
