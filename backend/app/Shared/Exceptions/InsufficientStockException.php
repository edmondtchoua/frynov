<?php

namespace App\Shared\Exceptions;

use RuntimeException;

class InsufficientStockException extends RuntimeException
{
    public function __construct(
        public readonly string $sku,
        public readonly int    $requested,
        public readonly int    $available,
    ) {
        parent::__construct(
            "Stock insuffisant pour {$sku} : demandé {$requested}, disponible {$available}"
        );
    }
}
