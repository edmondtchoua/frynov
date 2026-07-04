<?php

namespace App\Modules\Inventory\Exceptions;

use RuntimeException;

/**
 * RC-6D — valeur d'identifiant invalide pour sa définition (regex/format) ou vide après normalisation.
 */
class InvalidSerialException extends RuntimeException
{
    public function __construct(
        public readonly string $serialType,
        public readonly string $serialValue,
        ?string $help = null,
    ) {
        parent::__construct(
            "Identifiant {$serialType} invalide : « {$serialValue} »." . ($help ? " {$help}" : ''),
            422,
        );
    }
}
