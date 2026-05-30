<?php

namespace App\Modules\Inventory\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BatchDeliveryRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'items'                  => ['required', 'array', 'min:1', 'max:200'],
            'items.*.product_id'     => ['required', 'uuid', 'exists:products,id'],
            'items.*.variant_id'     => ['nullable', 'uuid', 'exists:product_variants,id'],
            'items.*.quantity'       => ['required', 'integer', 'min:1', 'max:10000'],
            'items.*.reference'      => ['nullable', 'string', 'max:100'],
            'items.*.note'           => ['nullable', 'string', 'max:500'],
            'reference'              => ['nullable', 'string', 'max:100'],
        ];
    }
}
