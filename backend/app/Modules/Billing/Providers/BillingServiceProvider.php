<?php

namespace App\Modules\Billing\Providers;

use App\Modules\Billing\Services\QuotaService;
use App\Shared\ModuleServiceProvider;

class BillingServiceProvider extends ModuleServiceProvider
{
    protected string $moduleName      = 'Billing';
    protected string $moduleNamespace = 'App\\Modules\\Billing';

    public function register(): void
    {
        parent::register();
        $this->app->singleton(QuotaService::class);
    }
}
