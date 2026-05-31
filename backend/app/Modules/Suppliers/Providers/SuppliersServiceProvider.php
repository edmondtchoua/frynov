<?php

namespace App\Modules\Suppliers\Providers;

use App\Shared\ModuleServiceProvider;

class SuppliersServiceProvider extends ModuleServiceProvider
{
    protected string $moduleName      = 'Suppliers';
    protected string $moduleNamespace = 'App\\Modules\\Suppliers';

    public function register(): void {}

    public function boot(): void
    {
        $this->loadMigrationsFrom($this->modulePath('database/migrations'));
        $this->loadRoutesFrom($this->modulePath('routes/api.php'));
    }
}
