<?php

namespace App\Modules\Catalog\Providers;

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
        // Bind ProductCodeService with its infrastructure dependencies
        $this->app->singleton(ProductCodeService::class, function ($app) {
            return new ProductCodeService(
                new QrCodeGenerator(),
                new BarcodeGeneratorSVG(),
            );
        });
    }

    public function boot(): void
    {
        parent::boot();
    }
}
