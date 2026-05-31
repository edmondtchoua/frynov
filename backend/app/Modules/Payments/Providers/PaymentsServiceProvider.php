<?php

namespace App\Modules\Payments\Providers;

use App\Shared\ModuleServiceProvider;

class PaymentsServiceProvider extends ModuleServiceProvider
{
    protected string $moduleName      = 'Payments';
    protected string $moduleNamespace = 'App\\Modules\\Payments';

    public function register(): void
    {
        // PaymentService resolved automatically — no interface binding needed
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom($this->modulePath('database/migrations'));
        $this->loadRoutesFrom($this->modulePath('routes/api.php'));
    }
}
