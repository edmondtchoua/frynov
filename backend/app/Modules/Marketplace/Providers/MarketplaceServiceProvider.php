<?php
namespace App\Modules\Marketplace\Providers;

use App\Modules\Inventory\Events\StockUpdated;
use App\Modules\Marketplace\Listeners\DispatchMarketplaceSync;
use App\Modules\Marketplace\Services\MarketplaceAdapterFactory;
use App\Shared\ModuleServiceProvider;
use Illuminate\Support\Facades\Event;

class MarketplaceServiceProvider extends ModuleServiceProvider
{
    protected string $moduleName      = 'Marketplace';
    protected string $moduleNamespace = 'App\\Modules\\Marketplace';

    public function register(): void
    {
        $this->app->singleton(MarketplaceAdapterFactory::class);
    }

    public function boot(): void
    {
        parent::boot();
        Event::listen(StockUpdated::class, DispatchMarketplaceSync::class);
    }
}
