<?php

namespace App\Modules\Inventory\Events;

use App\Modules\Inventory\Models\Stock;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LowStockDetected
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Stock $stock) {}
}
