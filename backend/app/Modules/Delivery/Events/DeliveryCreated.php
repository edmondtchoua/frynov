<?php

namespace App\Modules\Delivery\Events;

use App\Modules\Delivery\Models\Delivery;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DeliveryCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
public readonly Delivery $model,
    ) {}
}