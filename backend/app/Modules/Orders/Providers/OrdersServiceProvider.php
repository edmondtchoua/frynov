<?php

namespace App\Modules\Orders\Providers;

use App\Modules\Orders\Repositories\OrdersRepositoryInterface;
use App\Modules\Orders\Repositories\EloquentOrdersRepository;
use App\Shared\ModuleServiceProvider;

class OrdersServiceProvider extends ModuleServiceProvider
{
    protected string $moduleName      = 'Orders';
    protected string $moduleNamespace = 'App\\Modules\\Orders';

    public function register(): void
    {
// Binding interface → implémentation concrète
$this->app->bind(
    OrdersRepositoryInterface::class,
    EloquentOrdersRepository::class,
);
    }

    public function boot(): void
    {
$this->loadMigrationsFrom($this->modulePath('database/migrations'));
$this->loadRoutesFrom($this->modulePath('routes/api.php'));
    }
}