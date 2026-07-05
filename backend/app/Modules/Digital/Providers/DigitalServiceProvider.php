<?php

namespace App\Modules\Digital\Providers;

use App\Shared\ModuleServiceProvider;

class DigitalServiceProvider extends ModuleServiceProvider
{
    protected string $moduleName      = 'Digital';
    protected string $moduleNamespace = 'App\\Modules\\Digital';

    public function boot(): void
    {
        $this->loadMigrationsFrom($this->modulePath('database/migrations'));
        // Le fichier de routes porte déjà le préfixe complet `api/digital`.
        $this->loadRoutesFrom($this->modulePath('routes/api.php'));
    }
}
