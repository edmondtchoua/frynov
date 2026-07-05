<?php

namespace App\Modules\Pos\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CashRegisterSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'warehouse_id'        => $this->warehouse_id,
            'label'               => $this->label,
            'status'              => $this->status,

            // Amounts: both raw centimes and major units, like PaymentResource.
            'opening_float_cents' => $this->opening_float_cents,
            'total_sales_cents'   => $this->total_sales_cents,
            'cash_sales_cents'    => $this->cash_sales_cents,
            'sales_count'         => $this->sales_count,

            // Live expectation while open; persisted figures once closed.
            'expected_cash_cents' => $this->expected_cash_cents ?? $this->expectedCashNow(),
            'counted_cash_cents'  => $this->counted_cash_cents,
            'difference_cents'    => $this->difference_cents,

            'opened_by'           => $this->opened_by,
            'closed_by'           => $this->closed_by,
            'opened_at'           => $this->opened_at?->toISOString(),
            'closed_at'           => $this->closed_at?->toISOString(),
            'notes'               => $this->notes,
            'created_at'          => $this->created_at?->toISOString(),
        ];
    }
}
