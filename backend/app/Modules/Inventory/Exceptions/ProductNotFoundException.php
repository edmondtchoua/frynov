<?php

namespace App\Modules\Inventory\Exceptions;

use RuntimeException;

class ProductNotFoundException extends RuntimeException
{
    public function __construct(string $sku)
    {
        parent::__construct("No active product or variant found for SKU: {$sku}.", 404);
    }
}
