<?php

namespace App\Modules\Auth\Exceptions;

use RuntimeException;

class TenantInactiveException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Ce tenant est inactif ou suspendu.');
    }
}
