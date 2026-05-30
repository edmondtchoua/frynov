<?php

namespace App\Modules\Inventory\Providers;

use App\Modules\Inventory\Repositories\InventoryRepositoryInterface;
use App\Modules\Inventory\Repositories\EloquentInventoryRepository;
use App\Shared\ModuleServiceProvider;

class InventoryServiceProvider extends ModuleServiceProvider
{
    protected string $moduleName      = 'Inventory';
    protected string $moduleNamespace = 'App\\Modules\\Inventory';

    public function register(): void
    {
// Binding interface → implémentation concrète
$this->app->bind(
    InventoryRepositoryInterface::class,
    EloquentInventoryRepository::class,
);
    }

    public function boot(): void
    {
$this->loadMigrationsFrom($this->modulePath('database/migrations'));
$this->loadRoutesFrom($this->modulePath('routes/api.php'));
    }
}