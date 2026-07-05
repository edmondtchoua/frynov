<?php

namespace App\Modules\Catalog\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductVariantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $effectivePrice = $this->effectivePrice();

        return [
            'id'         => $this->id,
            'product_id' => $this->product_id,
            'sku'        => $this->sku,
            'name'       => $this->name,
            // label: combined N-axis label e.g. "30L / Rouge" — needed by frontend to reconstruct axes
            'label'      => $this->label ?? $this->name,
            'attributes' => $this->attributes,
            'price'      => [
                'amount'    => $effectivePrice->amount(),
                'currency'  => $effectivePrice->currency(),
                'formatted' => $effectivePrice->format(),
                'inherited' => $this->price_amount === null,
            ],
            'barcode'    => $this->barcode,
            'sort_order' => $this->sort_order,
            'is_active'  => $this->is_active,
        ];
    }
}
