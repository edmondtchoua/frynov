<?php

namespace App\Modules\Customers\Providers;

use App\Modules\Customers\Repositories\CustomersRepositoryInterface;
use App\Modules\Customers\Repositories\EloquentCustomersRepository;
use App\Shared\ModuleServiceProvider;

class CustomersServiceProvider extends ModuleServiceProvider
{
    protected string $moduleName      = 'Customers';
    protected string $moduleNamespace = 'App\\Modules\\Customers';

    public function register(): void
    {
// Binding interface → implémentation concrète
$this->app->bind(
    CustomersRepositoryInterface::class,
    EloquentCustomersRepository::class,
);
    }

    public function boot(): void
    {
$this->loadMigrationsFrom($this->modulePath('database/migrations'));
$this->loadRoutesFrom($this->modulePath('routes/api.php'));
    }
}