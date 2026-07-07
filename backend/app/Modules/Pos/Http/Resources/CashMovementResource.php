<?php

namespace App\Modules\Pos\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CashMovementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'session_id'    => $this->session_id,
            'direction'     => $this->direction,          // 'in' | 'out'
            'amount_cents'  => $this->amount_cents,
            'signed_cents'  => $this->signedAmount(),      // +in / −out, for drawer maths
            'reason'        => $this->reason,
            'note'          => $this->note,
            'order_id'      => $this->order_id,
            'performed_by'  => $this->performed_by,
            'created_at'    => $this->created_at?->toISOString(),
        ];
    }
}
