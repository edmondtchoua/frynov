<?php

namespace App\Modules\Orders\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateOrderRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'customer_id'                    => ['nullable', 'uuid'],
            'note'                           => ['nullable', 'string', 'max:500'],
            'items'                          => ['required', 'array', 'min:1'],
            'items.*.product_id'             => ['required', 'uuid'],
            'items.*.variant_id'             => ['nullable', 'uuid'],
            'items.*.quantity'               => ['required', 'integer', 'min:1'],
            'items.*.unit_price_cents'       => ['nullable', 'integer', 'min:0'],
        ];
    }
}
