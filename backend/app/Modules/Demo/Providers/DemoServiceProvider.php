<?php

namespace App\Modules\Demo\Providers;

use App\Shared\ModuleServiceProvider;

class DemoServiceProvider extends ModuleServiceProvider
{
    protected string $moduleName      = 'Demo';
    protected string $moduleNamespace = 'App\\Modules\\Demo';

    public function register(): void
    {
        parent::register();

        $this->mergeConfigFrom(base_path('config/demo.php'), 'demo');
    }
}
