<?php

namespace App\Modules\Inventory\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdjustStockRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'quantity'   => ['required', 'integer', 'min:0'],
            'note'       => ['required', 'string', 'min:5', 'max:500'],
            'variant_id' => ['nullable', 'uuid'],  // scopes adjustment to a variant
        ];
    }
}
