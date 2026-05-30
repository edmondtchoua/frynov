<?php

namespace App\Modules\Payments\Providers;

use App\Modules\Payments\Repositories\PaymentsRepositoryInterface;
use App\Modules\Payments\Repositories\EloquentPaymentsRepository;
use App\Shared\ModuleServiceProvider;

class PaymentsServiceProvider extends ModuleServiceProvider
{
    protected string $moduleName      = 'Payments';
    protected string $moduleNamespace = 'App\\Modules\\Payments';

    public function register(): void
    {
// Binding interface → implémentation concrète
$this->app->bind(
    PaymentsRepositoryInterface::class,
    EloquentPaymentsRepository::class,
);
    }

    public function boot(): void
    {
$this->loadMigrationsFrom($this->modulePath('database/migrations'));
$this->loadRoutesFrom($this->modulePath('routes/api.php'));
    }
}