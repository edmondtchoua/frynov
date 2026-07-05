<?php

namespace App\Modules\Pos\Events;

use App\Modules\Pos\Models\CashMovement;

/** RC-26 — mouvement de caisse (pay-in/pay-out hors vente). Consommé par le moteur d'imputation. */
class PosCashMovementRecorded
{
    public function __construct(public readonly CashMovement $movement) {}
}
