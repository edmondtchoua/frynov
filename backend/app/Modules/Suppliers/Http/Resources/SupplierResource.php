<?php

namespace App\Modules\Suppliers\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'code'          => $this->code,
            'name'          => $this->name,
            'email'         => $this->email,
            'phone'         => $this->phone,
            'contact_name'  => $this->contact_name,
            'address'       => $this->address,
            'payment_terms' => $this->payment_terms,
            'notes'         => $this->notes,
            'status'        => $this->status,
            'products_count'=> $this->whenCounted('products'),
            'created_at'    => $this->created_at->toISOString(),
            'updated_at'    => $this->updated_at->toISOString(),
        ];
    }
}
