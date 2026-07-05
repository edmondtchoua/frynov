<?php

namespace App\Modules\Inventory\Http\Requests;

use App\Modules\Inventory\Models\StockMovement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ScanActionRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'sku'       => ['required', 'string', 'max:100'],
            'action'    => ['required', Rule::in(['move_in', 'move_out', 'check'])],
            'quantity'  => ['required_unless:action,check', 'integer', 'min:1'],
            'reason'    => ['nullable', Rule::in([
                StockMovement::REASON_DELIVERY,
                StockMovement::REASON_SALE,
                StockMovement::REASON_RETURN,
                StockMovement::REASON_LOSS,
                StockMovement::REASON_MANUAL,
            ])],
            'reference' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function defaultReason(): string
    {
        return match ($this->action) {
            'move_in'  => StockMovement::REASON_DELIVERY,
            'move_out' => StockMovement::REASON_SALE,
            default    => StockMovement::REASON_MANUAL,
        };
    }
}
