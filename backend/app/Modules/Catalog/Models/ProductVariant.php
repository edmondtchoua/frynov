<?php

namespace App\Modules\Catalog\Models;

use App\Shared\ValueObjects\Money;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductVariant extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'product_id',
        'tenant_id',
        'sku',
        'name',
        'attributes',
        'price_amount',
        'price_currency',
        'cost_amount',
        'barcode',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'attributes' => 'array',
            'is_active'  => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** Returns the variant's own price, or inherits the parent product price. */
    public function effectivePrice(): Money
    {
        if ($this->price_amount !== null && $this->price_currency !== null) {
            return Money::of($this->price_amount, $this->price_currency);
        }

        return $this->product->price();
    }

    /** Effective SKU for code generation (always resolves). */
    public function codeSku(): string
    {
        return $this->sku;
    }
}
