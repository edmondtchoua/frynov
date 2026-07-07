<?php

namespace App\Modules\Suppliers\Policies;

use App\Shared\Authorization\ModulePolicy;

class SupplierPolicy extends ModulePolicy
{
    protected string $module = 'suppliers';
}
