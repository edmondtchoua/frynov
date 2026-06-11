<?php

namespace App\Modules\Inventory\Exceptions;

use RuntimeException;

/**
 * RC-5B — un identifiant sérialisé (IMEI/VIN/…) déjà présent pour ce tenant est rejeté (unicité).
 */
class DuplicateSerialException extends RuntimeException
{
    public function __construct(
        public readonly string $serialType,
        public readonly string $serialValue,
    ) {
        parent::__construct("Identifiant déjà enregistré pour ce compte : {$serialType} {$serialValue}");
    }
}
