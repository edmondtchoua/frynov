<?php

namespace App\Modules\Inventory\Exceptions;

use RuntimeException;

class StockLockException extends RuntimeException
{
    public function __construct(string $stockId)
    {
        parent::__construct("Could not acquire stock lock for {$stockId}. Retry.", 503);
    }
}
