<?php

namespace App\Modules\Inventory\Providers;

use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Inventory\Services\StockService;
use App\Shared\ModuleServiceProvider;

class InventoryServiceProvider extends ModuleServiceProvider
{
    protected string $moduleName      = 'Inventory';
    protected string $moduleNamespace = 'App\\Modules\\Inventory';

    public function register(): void
    {
        $this->app->singleton(StockService::class);

        $this->app->singleton(InventoryService::class, function ($app) {
            return new InventoryService($app->make(StockService::class));
        });
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom($this->modulePath('database/migrations'));
        $this->loadRoutesFrom($this->modulePath('routes/api.php'));
    }
}
