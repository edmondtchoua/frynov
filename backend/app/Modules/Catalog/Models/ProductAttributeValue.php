<?php

namespace App\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ProductAttributeValue extends Model
{
    use HasUuids;

    protected $fillable = ['attribute_id', 'label', 'value', 'color_hex', 'image_url', 'position'];
    protected function casts(): array { return ['position' => 'integer']; }

    public function attribute(): BelongsTo { return $this->belongsTo(ProductAttribute::class, 'attribute_id'); }

    public function variants(): BelongsToMany
    {
        return $this->belongsToMany(ProductVariant::class, 'product_variant_attr_values', 'attribute_value_id', 'variant_id');
    }
}
