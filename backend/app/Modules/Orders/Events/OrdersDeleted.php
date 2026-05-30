<?php

namespace App\Modules\Orders\Events;

use App\Modules\Orders\Models\Orders;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrdersDeleted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
public readonly Orders $model,
    ) {}
}