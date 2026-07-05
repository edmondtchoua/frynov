<?php

namespace App\Modules\Warranties\Providers;

use App\Shared\ModuleServiceProvider;

class WarrantiesServiceProvider extends ModuleServiceProvider
{
    protected string $moduleName      = 'Warranties';
    protected string $moduleNamespace = 'App\\Modules\\Warranties';

    public function boot(): void
    {
        $this->loadMigrationsFrom($this->modulePath('database/migrations'));
        // Le fichier de routes porte déjà le préfixe complet `api/warranties` (pas de double `api`).
        $this->loadRoutesFrom($this->modulePath('routes/api.php'));
    }
}
