<?php

namespace App\Modules\Delivery\Providers;

use App\Modules\Delivery\Repositories\DeliveryRepositoryInterface;
use App\Modules\Delivery\Repositories\EloquentDeliveryRepository;
use App\Shared\ModuleServiceProvider;

class DeliveryServiceProvider extends ModuleServiceProvider
{
    protected string $moduleName      = 'Delivery';
    protected string $moduleNamespace = 'App\\Modules\\Delivery';

    public function register(): void
    {
// Binding interface → implémentation concrète
$this->app->bind(
    DeliveryRepositoryInterface::class,
    EloquentDeliveryRepository::class,
);
    }

    public function boot(): void
    {
$this->loadMigrationsFrom($this->modulePath('database/migrations'));
$this->loadRoutesFrom($this->modulePath('routes/api.php'));
    }
}