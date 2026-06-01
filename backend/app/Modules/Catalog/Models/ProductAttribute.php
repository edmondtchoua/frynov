<?php

namespace App\Modules\Catalog\Models;

use App\Shared\Traits\HasTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductAttribute extends Model
{
    use HasTenant, HasUuids;

    protected $fillable = ['tenant_id', 'product_id', 'name', 'code', 'type', 'position'];
    protected function casts(): array { return ['position' => 'integer']; }

    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function values(): HasMany    { return $this->hasMany(ProductAttributeValue::class, 'attribute_id')->orderBy('position'); }
}
