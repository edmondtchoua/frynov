<?php

namespace App\Modules\Delivery\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'tenant_id'       => $this->tenant_id,
            'order_id'        => $this->order_id,
            'order_number'    => $this->whenLoaded('order', fn () => $this->order?->number),
            'status'          => $this->status,
            'address'         => $this->address,
            'carrier'         => $this->carrier,
            'tracking_number' => $this->tracking_number,
            'notes'           => $this->notes,
            'dispatched_at'   => $this->dispatched_at?->toISOString(),
            'delivered_at'    => $this->delivered_at?->toISOString(),
            'failed_at'       => $this->failed_at?->toISOString(),
            'failed_reason'   => $this->failed_reason,
            'created_at'      => $this->created_at->toISOString(),
            'updated_at'      => $this->updated_at->toISOString(),
        ];
    }
}
