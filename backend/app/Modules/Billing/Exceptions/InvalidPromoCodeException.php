<?php

namespace App\Modules\Billing\Exceptions;

use Exception;

class InvalidPromoCodeException extends Exception
{
    public function __construct(string $message = 'Code promotionnel invalide.')
    {
        parent::__construct($message);
    }
}
