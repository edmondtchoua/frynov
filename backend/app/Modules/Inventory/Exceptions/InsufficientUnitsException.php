<?php

namespace App\Modules\Inventory\Exceptions;

use RuntimeException;

/**
 * RC-5C — pas assez d'unités sérialisées DISPONIBLES (status=in_stock) pour satisfaire une ligne de
 * commande. Distincte de InsufficientStockException (stock agrégé) : ici c'est le compte d'unités
 * physiques identifiées (IMEI/VIN) qui manque, même si le miroir agrégé semblait suffisant.
 */
class InsufficientUnitsException extends RuntimeException
{
    public function __construct(
        public readonly string $sku,
        public readonly int $available,
        public readonly int $requested,
    ) {
        parent::__construct(
            "Pas assez d'unités sérialisées disponibles pour {$sku} : demandées {$requested}, disponibles {$available}.",
            422,
        );
    }
}
