<?php

namespace App\Modules\Orders\Exceptions;

use RuntimeException;

class OrderNotFoundException extends RuntimeException
{
    public function __construct(string $id)
    {
        parent::__construct("Order '{$id}' not found.");
    }
}
