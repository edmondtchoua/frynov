<?php

namespace App\Modules\Payments\Policies;

use App\Shared\Authorization\ModulePolicy;

/**
 * 2ᵉ ligne de défense (audit RBAC P2). Miroir exact des routes paiements :
 * `store` → `payments.create`, `destroy` → `payments.delete` (per-action, aucun durcissement d'accès).
 */
class PaymentPolicy extends ModulePolicy
{
    protected string $module = 'payments';
}
