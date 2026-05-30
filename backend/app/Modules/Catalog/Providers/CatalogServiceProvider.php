<?php

namespace App\Modules\Catalog\Providers;

use App\Modules\Catalog\Services\LabelService;
use App\Modules\Catalog\Services\ProductCodeService;
use App\Shared\ModuleServiceProvider;
use Picqer\Barcode\BarcodeGeneratorSVG;
use SimpleSoftwareIO\QrCode\Generator as QrCodeGenerator;

class CatalogServiceProvider extends ModuleServiceProvider
{
    protected string $moduleName      = 'Catalog';
    protected string $moduleNamespace = 'App\\Modules\\Catalog';

    public function register(): void
    {
        $this->app->singleton(ProductCodeService::class, function () {
            return new ProductCodeService(
                new QrCodeGenerator(),
                new BarcodeGeneratorSVG(),
            );
        });

        $this->app->singleton(LabelService::class, function ($app) {
            return new LabelService($app->make(ProductCodeService::class));
        });
    }

    public function boot(): void
    {
        parent::boot();

        // Register Blade namespace 'catalog::' for label views
        $this->loadViewsFrom($this->modulePath('resources/views'), 'catalog');
    }
}
