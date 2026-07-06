<?php

namespace App\Modules\Billing\Providers;

use App\Modules\Billing\Services\Psp\FakePspGateway;
use App\Modules\Billing\Services\Psp\PspGateway;
use App\Modules\Billing\Services\QuotaService;
use App\Shared\ModuleServiceProvider;

class BillingServiceProvider extends ModuleServiceProvider
{
    protected string $moduleName      = 'Billing';
    protected string $moduleNamespace = 'App\\Modules\\Billing';

    public function register(): void
    {
        parent::register();
        $this->app->singleton(QuotaService::class);

        // Option PSP — driver résolu par config (seul `fake` est implémenté pour l'instant).
        $this->app->bind(PspGateway::class, fn () => match (config('billing.psp.driver')) {
            default => new FakePspGateway(),
        });
    }
}
