<?php

namespace App\Modules\Orders\Providers;

use App\Modules\Inventory\Services\StockService;
use App\Modules\Orders\Services\OrderService;
use App\Shared\ModuleServiceProvider;

class OrdersServiceProvider extends ModuleServiceProvider
{
    protected string $moduleName      = 'Orders';
    protected string $moduleNamespace = 'App\\Modules\\Orders';

    public function register(): void
    {
        $this->app->singleton(OrderService::class, function ($app) {
            return new OrderService($app->make(StockService::class));
        });
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom($this->modulePath('database/migrations'));
        $this->loadRoutesFrom($this->modulePath('routes/api.php'));
    }
}
