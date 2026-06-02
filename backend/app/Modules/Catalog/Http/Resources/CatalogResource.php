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
        $costMoney      = $this->cost();

        return [
            'id'           => $this->id,
            'sku'          => $this->sku,
            'name'         => $this->name,
            'description'  => $this->description,
            'status'       => $this->status,
            'product_type' => $this->product_type ?? 'simple',
            'category'     => new CategoryResource($this->whenLoaded('category')),
            // Supplier — populated when relation is loaded
            'supplier'     => $this->whenLoaded('supplier', fn () => $this->supplier ? [
                'id'   => $this->supplier->id,
                'name' => $this->supplier->name,
                'code' => $this->supplier->code ?? null,
            ] : null),
            'supplier_id'  => $this->supplier_id,
            'price'        => [
                'amount'    => $price->amount(),
                'currency'  => $price->currency(),
                'formatted' => $price->format(),
            ],
            'compare_at_price' => $compareAtPrice ? [
                'amount'    => $compareAtPrice->amount(),
                'currency'  => $compareAtPrice->currency(),
                'formatted' => $compareAtPrice->format(),
            ] : null,
            'cost'         => $costMoney ? [
                'amount'    => $costMoney->amount(),
                'currency'  => $costMoney->currency(),
                'formatted' => $costMoney->format(),
            ] : null,
            'is_on_sale'   => $this->isOnSale(),
            'has_variants' => $this->has_variants,
            'variants'     => ProductVariantResource::collection($this->whenLoaded('variants')),
            // Barcode / identifier fields
            'barcode'                => $this->barcode,
            'internal_barcode'       => $this->internal_barcode,
            'gtin'                   => $this->gtin,
            'barcode_type'           => $this->barcode_type,
            'barcode_source'         => $this->barcode_source,
            'barcode_auto_generated' => $this->barcode_auto_generated,
            'weight_kg'  => $this->weight_kg,
            'metadata'   => $this->metadata,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
