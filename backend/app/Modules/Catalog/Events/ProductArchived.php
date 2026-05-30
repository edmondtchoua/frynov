<?php

namespace App\Modules\Catalog\Events;

use App\Modules\Catalog\Models\Product;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProductArchived
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Product $product) {}
}
