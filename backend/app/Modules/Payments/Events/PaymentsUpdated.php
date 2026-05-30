<?php

namespace App\Modules\Payments\Events;

use App\Modules\Payments\Models\Payments;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentsUpdated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
public readonly Payments $model,
    ) {}
}