<?php

namespace App\Modules\Pos\Events;

use App\Modules\Orders\Models\OrderReturn;

/**
 * RC-26 — un remboursement au comptoir a été acté (RMA restocké + éventuel leg caisse).
 * `$refundMethod` : mode de remboursement (cash/mobile_money…), pilote le compte de trésorerie crédité.
 */
class PosSaleRefunded
{
    public function __construct(
        public readonly OrderReturn $return,
        public readonly string $refundMethod,
    ) {}
}
