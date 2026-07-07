<?php

namespace App\Modules\Orders\Policies;

use App\Shared\Authorization\ModulePolicy;

/**
 * 2ᵉ ligne de défense (audit RBAC P2). Seule la création est adossée à cette policy :
 * `store` → `orders.create` (miroir exact de la route). Le cycle de vie (confirm/fulfill/cancel)
 * reste gardé par `orders.manage` au niveau route — taxonomie distincte, non mappée ici.
 */
class OrderPolicy extends ModulePolicy
{
    protected string $module = 'orders';
}
