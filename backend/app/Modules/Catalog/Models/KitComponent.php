<?php

namespace App\Modules\Catalog\Models;

use App\Shared\Traits\HasTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * RC-6I — ligne de nomenclature d'un kit (composant + quantité par kit).
 */
class KitComponent extends Model
{
    use HasTenant, HasUuids;

    protected $table = 'kit_components';

    protected $fillable = [
        'tenant_id', 'kit_product_id', 'component_product_id', 'component_variant_id', 'quantity',
    ];

    protected function casts(): array
    {
        return ['quantity' => 'integer'];
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'component_product_id');
    }

    public function toApiArray(): array
    {
        return [
            'id'                   => $this->id,
            'component_product_id' => $this->component_product_id,
            'component_variant_id' => $this->component_variant_id,
            'component_name'       => $this->component?->name,
            'component_sku'        => $this->component?->sku,
            'quantity'             => $this->quantity,
        ];
    }
}
