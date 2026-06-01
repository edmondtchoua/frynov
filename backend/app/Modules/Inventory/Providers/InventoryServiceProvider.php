<?php

namespace App\Modules\Inventory\Providers;

use App\Modules\Inventory\Events\LowStockDetected;
use App\Modules\Inventory\Listeners\NotifyLowStock;
use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Inventory\Services\PeriodLockService;
use App\Modules\Inventory\Services\StockAdjustmentService;
use App\Modules\Inventory\Services\StockService;
use App\Modules\Inventory\Services\StockTransferService;
use App\Shared\ModuleServiceProvider;
use Illuminate\Support\Facades\Event;

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

        $this->app->singleton(StockAdjustmentService::class, function ($app) {
            return new StockAdjustmentService($app->make(StockService::class));
        });

        $this->app->singleton(StockTransferService::class, function ($app) {
            return new StockTransferService($app->make(StockService::class));
        });
        $this->app->singleton(PeriodLockService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom($this->modulePath('database/migrations'));
        $this->loadRoutesFrom($this->modulePath('routes/api.php'));

        // ── Event → Listener bindings ─────────────────────────────────────
        Event::listen(LowStockDetected::class, NotifyLowStock::class);
    }
}
