<?php

namespace App\Modules\Customers\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'name'         => $this->name,
            'email'        => $this->email,
            'phone'        => $this->phone,
            'address'      => $this->formatAddress($this->address),
            'notes'        => $this->notes,
            'orders_count' => $this->whenCounted('orders'),
            'created_at'   => $this->created_at?->toISOString(),
            'updated_at'   => $this->updated_at?->toISOString(),
        ];
    }

    private function formatAddress(mixed $address): ?array
    {
        if ($address === null || $address === '') {
            return null;
        }

        if (is_string($address)) {
            return ['street' => $address];
        }

        if (is_array($address)) {
            $normalized = array_filter([
                'street'  => $address['street'] ?? null,
                'city'    => $address['city'] ?? null,
                'zip'     => $address['zip'] ?? null,
                'country' => $address['country'] ?? null,
            ], fn ($value) => $value !== null && $value !== '');

            return $normalized ?: null;
        }

        return null;
    }
}
