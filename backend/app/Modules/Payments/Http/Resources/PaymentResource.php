<?php

namespace App\Modules\Payments\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'order_id'     => $this->order_id,
            'order_number' => $this->whenLoaded('order', fn () => $this->order?->number),
            'amount_cents' => $this->amount_cents,
            'amount'       => $this->amount_cents / 100,
            'currency'     => $this->currency,
            'method'       => $this->method,
            'reference'    => $this->reference,
            'note'         => $this->note,
            'paid_at'      => $this->paid_at?->toISOString(),
            'created_at'   => $this->created_at?->toISOString(),
        ];
    }
}
