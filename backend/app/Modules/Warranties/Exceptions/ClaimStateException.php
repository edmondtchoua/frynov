<?php

namespace App\Modules\Warranties\Exceptions;

use RuntimeException;

/**
 * RC-5F — transition de statut invalide sur une réclamation SAV (ex. sortir d'un statut terminal).
 */
class ClaimStateException extends RuntimeException
{
    public function __construct(
        public readonly string $from,
        public readonly string $to,
    ) {
        parent::__construct("Transition SAV invalide : {$from} → {$to}.", 422);
    }
}
