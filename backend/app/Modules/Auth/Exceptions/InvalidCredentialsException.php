<?php

namespace App\Modules\Auth\Exceptions;

use RuntimeException;

class InvalidCredentialsException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Identifiants invalides.');
    }
}
