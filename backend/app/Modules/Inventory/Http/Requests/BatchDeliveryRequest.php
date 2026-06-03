<?php

namespace App\Modules\Inventory\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BatchDeliveryRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'items'                  => ['required', 'array', 'min:1', 'max:200'],
            // Sprint 11 fix — scoped to tenant to prevent cross-tenant IDOR via product_id injection
            'items.*.product_id'     => ['required', 'uuid', Rule::exists('products', 'id')->where('tenant_id', auth()->user()->tenant_id)],
            'items.*.variant_id'     => ['nullable', 'uuid', Rule::exists('product_variants', 'id')->where('tenant_id', auth()->user()->tenant_id)],
            'items.*.quantity'       => ['required', 'integer', 'min:1', 'max:10000'],
            // unit_cost_cents feeds the CMUP (weighted avg cost). A negative value
            // would corrupt it — validate min:0. Read by InventoryService::receiveDelivery().
            'items.*.unit_cost_cents'=> ['nullable', 'integer', 'min:0', 'max:1000000000'],
            'items.*.reference'      => ['nullable', 'string', 'max:100'],
            'items.*.note'           => ['nullable', 'string', 'max:500'],
            'reference'              => ['nullable', 'string', 'max:100'],
        ];
    }
}
