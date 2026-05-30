<?php

namespace App\Shared;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

abstract class ModuleServiceProvider extends ServiceProvider
{
    protected string $moduleName      = '';
    protected string $moduleNamespace = '';

    public function boot(): void
    {
        $migrations = $this->modulePath('database/migrations');

        if (is_dir($migrations)) {
            $this->loadMigrationsFrom($migrations);
        }

        $routes = $this->modulePath('routes/api.php');

        if (file_exists($routes)) {
            $this->loadApiRoutes($routes);
        }
    }

    public function register(): void {}

    protected function modulePath(string $path = ''): string
    {
        $base = app_path("Modules/{$this->moduleName}");

        return $path ? "{$base}/{$path}" : $base;
    }

    /**
     * Load routes under the 'api' middleware group with /api prefix.
     * Module route files only need to define their own prefix (e.g. Route::prefix('orders')).
     */
    protected function loadApiRoutes(string $path): void
    {
        Route::middleware('api')
            ->prefix('api')
            ->group($path);
    }
}
