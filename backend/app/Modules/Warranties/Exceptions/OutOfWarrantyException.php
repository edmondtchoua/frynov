<?php

namespace App\Modules\Warranties\Exceptions;

use RuntimeException;

/**
 * RC-5F — réclamation SAV ouverte sur un contrat hors période (ou void) sans override autorisé.
 */
class OutOfWarrantyException extends RuntimeException
{
    public function __construct(public readonly string $contractId, string $detail = 'hors période de garantie')
    {
        parent::__construct("Réclamation refusée : {$detail}.", 422);
    }
}
