<?php

namespace App\Modules\Orders\Exceptions;

use RuntimeException;

class OrderStateException extends RuntimeException
{
    public function __construct(string $orderId, string $action, string $currentStatus)
    {
        parent::__construct(
            "Cannot {$action} order '{$orderId}': current status is '{$currentStatus}'."
        );
    }
}
