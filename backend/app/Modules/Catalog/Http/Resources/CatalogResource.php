<?php

namespace App\Modules\Catalog\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** ProductResource — kept as CatalogResource for backward compat with existing ServiceProvider wiring. */
class CatalogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $price          = $this->price();
        $compareAtPrice = $this->compareAtPrice();

        return [
            'id'          => $this->id,
            'sku'         => $this->sku,
            'name'        => $this->name,
            'description' => $this->description,
            'status'      => $this->status,
            'category'    => new CategoryResource($this->whenLoaded('category')),
            'price'       => [
                'amount'    => $price->amount(),
                'currency'  => $price->currency(),
                'formatted' => $price->format(),
            ],
            'compare_at_price' => $compareAtPrice ? [
                'amount'    => $compareAtPrice->amount(),
                'currency'  => $compareAtPrice->currency(),
                'formatted' => $compareAtPrice->format(),
            ] : null,
            'is_on_sale'  => $this->isOnSale(),
            'has_variants' => $this->has_variants,
            'variants'    => ProductVariantResource::collection($this->whenLoaded('variants')),
            'barcode'              => $this->barcode,
            'internal_barcode'     => $this->internal_barcode,
            'gtin'                 => $this->gtin,
            'barcode_type'         => $this->barcode_type,
            'barcode_source'       => $this->barcode_source,
            'barcode_auto_generated' => $this->barcode_auto_generated,
            'weight_kg'   => $this->weight_kg,
            'metadata'    => $this->metadata,
            'created_at'  => $this->created_at?->toISOString(),
            'updated_at'  => $this->updated_at?->toISOString(),
        ];
    }
}
