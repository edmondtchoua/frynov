<?php

namespace App\Modules\Catalog\Models;

use App\Shared\ValueObjects\Money;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Shared\Traits\HasTenant;

class ProductVariant extends Model
{
    use HasFactory, HasTenant, HasUuids, SoftDeletes;

    protected $fillable = [
        'product_id',
        'tenant_id',
        'sku',
        'name',
        'label',        // human-readable combined label e.g. "S / Rouge" (Sprint 16 multi-axis)
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

    /**
     * Normalised attribute values for this variant (P1 — replaces JSON blob).
     * Ordered by attribute position so the label is always Couleur → Taille → RAM.
     */
    public function attributeValues(): BelongsToMany
    {
        return $this->belongsToMany(
            \App\Modules\Catalog\Models\ProductAttributeValue::class,
            'product_variant_attr_values',
            'variant_id',
            'attribute_value_id',
        )->with('attribute:id,code,name,position')
         ->orderBy('product_attributes.position');
    }

    /**
     * Returns a flat map of attribute code → label.
     * Example: ['color' => 'Rouge', 'size' => 'L', 'ram' => '8 Go']
     */
    public function getAttributeMapAttribute(): array
    {
        return $this->attributeValues
            ->keyBy(fn ($v) => $v->attribute?->code ?? $v->id)
            ->map(fn ($v) => $v->label)
            ->toArray();
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
