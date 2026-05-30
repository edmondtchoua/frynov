<?php

namespace App\Modules\Customers\Events;

use App\Modules\Customers\Models\Customers;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CustomersUpdated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
public readonly Customers $model,
    ) {}
}