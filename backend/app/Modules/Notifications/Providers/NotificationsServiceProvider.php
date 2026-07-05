<?php

namespace App\Modules\Notifications\Providers;

use App\Shared\ModuleServiceProvider;

class NotificationsServiceProvider extends ModuleServiceProvider
{
    protected string $moduleName      = 'Notifications';
    protected string $moduleNamespace = 'App\\Modules\\Notifications';

    public function boot(): void
    {
        $this->loadMigrationsFrom($this->modulePath('database/migrations'));
        // Le fichier de routes porte déjà le préfixe complet `api/notifications`.
        $this->loadRoutesFrom($this->modulePath('routes/api.php'));
    }
}
