<?php

namespace App\Modules\Pos\Events;

use App\Modules\Orders\Models\Order;

/**
 * RC-26 — une vente POS a été encaissée (order fulfilled + paiements). Consommé par le moteur
 * d'imputation comptable. `$legs` = [['method','amount'],…] (part par moyen de paiement).
 */
class PosSaleCompleted
{
    /** @param array<int,array{method:string,amount:int}> $legs */
    public function __construct(
        public readonly Order $order,
        public readonly array $legs,
    ) {}
}
