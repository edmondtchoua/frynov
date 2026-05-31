<?php

namespace App\Modules\Billing\Providers;

use App\Shared\ModuleServiceProvider;

class BillingServiceProvider extends ModuleServiceProvider
{
    protected string $moduleName      = 'Billing';
    protected string $moduleNamespace = 'App\\Modules\\Billing';
}
