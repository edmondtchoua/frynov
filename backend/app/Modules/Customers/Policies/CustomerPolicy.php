<?php

namespace App\Modules\Customers\Policies;

use App\Shared\Authorization\ModulePolicy;

class CustomerPolicy extends ModulePolicy
{
    protected string $module = 'customers';
}
