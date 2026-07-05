<?php

namespace App\Modules\Inventory\Http\Requests;

use App\Modules\Inventory\Models\StockMovement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MoveStockRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'quantity'   => ['required', 'integer', 'min:1'],
            'reason'     => ['required', Rule::in([
                StockMovement::REASON_DELIVERY,
                StockMovement::REASON_SALE,
                StockMovement::REASON_RETURN,
                StockMovement::REASON_LOSS,
                StockMovement::REASON_COUNT,
                StockMovement::REASON_MANUAL,
            ])],
            // variant_id — optional, scopes the stock movement to a specific variant
            'variant_id' => ['nullable', 'uuid'],
            // warehouse_id — optional, targets a specific location (defaults to tenant default)
            'warehouse_id' => ['nullable', 'uuid'],
            // unit_cost_cents — optional purchase cost for this receipt (feeds CMUP on move-in)
            'unit_cost_cents' => ['nullable', 'integer', 'min:0'],
            'reference'  => ['nullable', 'string', 'max:100'],
            'note'       => ['nullable', 'string', 'max:500'],
        ];
    }
}
