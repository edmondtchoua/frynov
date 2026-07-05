<?php

namespace App\Modules\Inventory\Models;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Shared\Traits\HasTenant;

class Stock extends Model
{
    use HasTenant, HasUuids;

    protected $fillable = [
        'tenant_id',
        'warehouse_id',   // which warehouse holds this stock record
        'product_id',
        'variant_id',
        'quantity',
        'reserved_quantity',
        'low_stock_threshold',
        'unit_cost_cents',
        'total_value_cents',
    ];

    protected $appends = ['available', 'is_low_stock'];

    protected function casts(): array
    {
        return [
            'quantity'            => 'integer',
            'reserved_quantity'   => 'integer',
            'low_stock_threshold' => 'integer',
            'unit_cost_cents'     => 'integer',
            'total_value_cents'   => 'integer',
        ];
    }

    // â”€â”€ Computed â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    /**
     * Expose computed available quantity in JSON serialization.
     * Named 'available_qty' to avoid conflict with the available() method.
     */
    public function getAvailableAttribute(): int
    {
        return $this->available();
    }

    public function getIsLowStockAttribute(): bool
    {
        return $this->isLowStock();
    }

    public function available(): int
    {
        return max(0, $this->quantity - $this->reserved_quantity);
    }

    public function isLowStock(): bool
    {
        return $this->quantity <= $this->low_stock_threshold;
    }

    // â”€â”€ Relationships â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

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

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class)->latest();
    }
}
