<?php

namespace App\Modules\Pos\Events;

use App\Modules\Pos\Models\CashRegisterSession;

/** RC-26 — clôture de caisse : l'écart (difference_cents) éventuel devient une écriture d'ajustement. */
class PosSessionClosed
{
    public function __construct(public readonly CashRegisterSession $session) {}
}
