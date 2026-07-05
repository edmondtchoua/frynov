<?php

namespace App\Modules\Catalog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization logic is handled by Policies
    }

    public function rules(): array
    {
        return [
            'sku'               => ['nullable', 'string', 'max:50'],
            'internal_barcode'  => ['nullable', 'string', 'max:50'],
            'gtin'              => ['nullable', 'string', 'max:20'],
            'barcode_type'      => ['nullable', 'in:INTERNAL,EAN13,UPC_A,GTIN_13,GTIN_14,CODE128,QR'],
            'barcode'           => ['nullable', 'string', 'max:255'],
        ];
    }
}
