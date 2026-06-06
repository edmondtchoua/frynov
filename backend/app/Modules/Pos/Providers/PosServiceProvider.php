<?php

namespace App\Modules\Pos\Providers;

use App\Shared\ModuleServiceProvider;

class PosServiceProvider extends ModuleServiceProvider
{
    protected string $moduleName      = 'Pos';
    protected string $moduleNamespace = 'App\\Modules\\Pos';

    public function register(): void
    {
        // PosService is resolved automatically (its OrderService / PaymentService /
        // AuditService dependencies are container-bound) — no manual binding needed.
    }

    /**
     * Override the base boot(): our routes file already carries the full `api/pos`
     * prefix and its own middleware (like the Payments module), so load it directly
     * instead of via loadApiRoutes(), which would prepend a second `api/` prefix.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom($this->modulePath('database/migrations'));
        $this->loadRoutesFrom($this->modulePath('routes/api.php'));
    }
}
