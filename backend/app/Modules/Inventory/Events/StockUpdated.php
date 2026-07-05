<?php
namespace App\Modules\Inventory\Events;
use App\Modules\Inventory\Models\Stock;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StockUpdated
{
    use Dispatchable, SerializesModels;
    public function __construct(
        public readonly Stock  $stock,
        public readonly int    $quantityChange,  // positive = in, negative = out
        public readonly string $source,           // pos|web|api|marketplace:{platform}
    ) {}
}
