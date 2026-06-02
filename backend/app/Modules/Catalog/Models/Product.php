<?php

namespace App\Modules\Catalog\Models;

use App\Shared\ValueObjects\Money;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Shared\Traits\HasTenant;

class Product extends Model
{
    use HasFactory, HasTenant, HasUuids, SoftDeletes;

    // Valid product types
    public const TYPE_SIMPLE   = 'simple';
    public const TYPE_VARIABLE = 'variable';
    public const TYPE_SERVICE  = 'service';
    public const TYPE_KIT      = 'kit';

    protected $fillable = [
        'tenant_id',
        'category_id',
        'supplier_id',
        'sku',
        'name',
        'description',
        'price_amount',
        'price_currency',
        'compare_at_price_amount',
        'cost_amount',
        'status',
        'has_variants',
        'product_type',         // Sprint 17: simple|variable|service|kit
        'barcode',
        'internal_barcode',
        'gtin',
        'barcode_type',
        'barcode_source',
        'barcode_auto_generated',
        'weight_kg',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'has_variants'           => 'boolean',
            'barcode_auto_generated' => 'boolean',
            'metadata'               => 'array',
            'weight_kg'              => 'decimal:3',
        ];
    }

    // ── Type helpers ──────────────────────────────────────────────────────────

    public function isVariable(): bool
    {
        return $this->product_type === self::TYPE_VARIABLE || $this->has_variants;
    }

    public function isService(): bool
    {
        return $this->product_type === self::TYPE_SERVICE;
    }

    public function isStockable(): bool
    {
        return $this->product_type !== self::TYPE_SERVICE;
    }

    // â”€â”€ Money accessors â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function price(): Money
    {
        return Money::of($this->price_amount, $this->price_currency);
    }

    public function compareAtPrice(): ?Money
    {
        if ($this->compare_at_price_amount === null) {
            return null;
        }

        return Money::of($this->compare_at_price_amount, $this->price_currency);
    }

    public function cost(): ?Money
    {
        if ($this->cost_amount === null) {
            return null;
        }

        return Money::of($this->cost_amount, $this->price_currency);
    }

    public function isOnSale(): bool
    {
        return $this->compare_at_price_amount !== null
            && $this->compare_at_price_amount > $this->price_amount;
    }

    // â”€â”€ Status helpers â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    // â”€â”€ Relationships â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Suppliers\Models\Supplier::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('sort_order');
    }

    /** Normalized attribute axes for this product. */
    public function attributes(): HasMany
    {
        return $this->hasMany(ProductAttribute::class)->orderBy('position');
    }

    /** Stock records across all warehouses for this product (no variant). */
    public function stocks(): HasMany
    {
        return $this->hasMany(\App\Modules\Inventory\Models\Stock::class);
    }
}
