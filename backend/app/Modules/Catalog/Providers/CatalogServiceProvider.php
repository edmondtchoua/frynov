<?php

namespace App\Modules\Catalog\Providers;

use App\Modules\Catalog\Repositories\CatalogRepositoryInterface;
use App\Modules\Catalog\Repositories\EloquentCatalogRepository;
use App\Shared\ModuleServiceProvider;

class CatalogServiceProvider extends ModuleServiceProvider
{
    protected string $moduleName      = 'Catalog';
    protected string $moduleNamespace = 'App\\Modules\\Catalog';

    public function register(): void
    {
// Binding interface → implémentation concrète
$this->app->bind(
    CatalogRepositoryInterface::class,
    EloquentCatalogRepository::class,
);
    }

    public function boot(): void
    {
$this->loadMigrationsFrom($this->modulePath('database/migrations'));
$this->loadRoutesFrom($this->modulePath('routes/api.php'));
    }
}