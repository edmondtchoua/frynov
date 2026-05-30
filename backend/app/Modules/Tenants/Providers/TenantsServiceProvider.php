<?php

namespace App\Modules\Tenants\Providers;

use App\Shared\ModuleServiceProvider;

class TenantsServiceProvider extends ModuleServiceProvider
{
    protected string $moduleName      = 'Tenants';
    protected string $moduleNamespace = 'App\\Modules\\Tenants';

    public function register(): void {}

    public function boot(): void
    {
        parent::boot();
    }
}
