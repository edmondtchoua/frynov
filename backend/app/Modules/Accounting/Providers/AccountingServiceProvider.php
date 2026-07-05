<?php

namespace App\Modules\Accounting\Providers;

use App\Modules\Accounting\Listeners\PosAccountingSubscriber;
use App\Shared\ModuleServiceProvider;
use Illuminate\Support\Facades\Event;

class AccountingServiceProvider extends ModuleServiceProvider
{
    protected string $moduleName      = 'Accounting';
    protected string $moduleNamespace = 'App\\Modules\\Accounting';

    /**
     * Comme Pos/Payments : le fichier de routes porte déjà son préfixe `api/accounting` et ses
     * middlewares — chargé directement (loadApiRoutes ajouterait un second préfixe `api/`).
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom($this->modulePath('database/migrations'));
        $this->loadRoutesFrom($this->modulePath('routes/api.php'));

        // RC-26 — la comptabilité écoute les événements POS (dépendance à sens unique).
        Event::subscribe(PosAccountingSubscriber::class);
    }
}
