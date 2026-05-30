<?php

namespace App\Modules\Tenants\Providers;

use App\Shared\ModuleServiceProvider;

class TenantsServiceProvider extends ModuleServiceProvider
{
    protected string $moduleName      = 'Tenants';
    protected string $moduleNamespace = 'App\\Modules\\Tenants';

    public function register(): void
    {
        // Pas de binding repository pour ce module (service simple)
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom($this->modulePath('database/migrations'));
    }
}
