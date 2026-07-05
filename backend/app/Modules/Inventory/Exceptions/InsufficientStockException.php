<?php

namespace App\Modules\Inventory\Exceptions;

use RuntimeException;

class InsufficientStockException extends RuntimeException
{
    public function __construct(
        public readonly string $sku,
        public readonly int $available,
        public readonly int $requested,
    ) {
        parent::__construct(
            "Insufficient stock for {$sku}: requested {$requested}, available {$available}.",
            422,
        );
    }
}
