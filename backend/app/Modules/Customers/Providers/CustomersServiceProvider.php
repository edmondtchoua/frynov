<?php

namespace App\Modules\Customers\Providers;

use App\Shared\ModuleServiceProvider;

class CustomersServiceProvider extends ModuleServiceProvider
{
    protected string $moduleName      = 'Customers';
    protected string $moduleNamespace = 'App\\Modules\\Customers';

    public function register(): void
    {
        // CustomerService is resolved via the container automatically (no interface needed)
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom($this->modulePath('database/migrations'));
        $this->loadRoutesFrom($this->modulePath('routes/api.php'));
    }
}
