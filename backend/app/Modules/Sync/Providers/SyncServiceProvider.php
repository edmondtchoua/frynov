<?php

namespace App\Modules\Sync\Providers;

use App\Modules\Sync\Repositories\SyncRepositoryInterface;
use App\Modules\Sync\Repositories\EloquentSyncRepository;
use App\Shared\ModuleServiceProvider;

class SyncServiceProvider extends ModuleServiceProvider
{
    protected string $moduleName      = 'Sync';
    protected string $moduleNamespace = 'App\\Modules\\Sync';

    public function register(): void
    {
// Binding interface → implémentation concrète
$this->app->bind(
    SyncRepositoryInterface::class,
    EloquentSyncRepository::class,
);
    }

    public function boot(): void
    {
$this->loadMigrationsFrom($this->modulePath('database/migrations'));
$this->loadRoutesFrom($this->modulePath('routes/api.php'));
    }
}