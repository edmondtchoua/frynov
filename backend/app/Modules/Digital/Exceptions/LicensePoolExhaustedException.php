<?php

namespace App\Modules\Digital\Exceptions;

use RuntimeException;

/**
 * RC-6E — pool de clés épuisé et politique du plan = `block` : la livraison échoue tant que des clés
 * ne sont pas importées (la commande reste confirmée).
 */
class LicensePoolExhaustedException extends RuntimeException
{
    public function __construct(public readonly string $productName)
    {
        parent::__construct(
            "Pool de clés de licence épuisé pour « {$productName} » : importez des clés avant de livrer.",
            422,
        );
    }
}
