<?php

namespace App\Shared;

use Illuminate\Support\ServiceProvider;

/**
 * Classe de base pour tous les ServiceProviders de modules.
 * Chaque module étend cette classe et surcharge les méthodes nécessaires.
 */
abstract class ModuleServiceProvider extends ServiceProvider
{
    /**
     * Nom du module (utilisé pour les routes, views, lang, etc.)
     * Exemple : 'orders', 'inventory', 'catalog'
     */
    protected string $moduleName = '';

    /**
     * Namespace du module.
     * Exemple : 'App\Modules\Orders'
     */
    protected string $moduleNamespace = '';

    public function boot(): void
    {
        $this->loadMigrationsFrom($this->modulePath('database/migrations'));
        $this->loadRoutesFrom($this->modulePath('routes/api.php'));
    }

    public function register(): void
    {
        $this->app->register($this->moduleEventServiceProvider());
    }

    /**
     * Chemin absolu vers un sous-dossier du module.
     */
    protected function modulePath(string $path = ''): string
    {
        $base = app_path("Modules/{$this->moduleName}");

        return $path ? "{$base}/{$path}" : $base;
    }

    /**
     * Retourne le nom FQCN du EventServiceProvider du module.
     * Le module peut surcharger cette méthode si il n'en a pas.
     */
    protected function moduleEventServiceProvider(): string
    {
        return "{$this->moduleNamespace}\\Providers\\EventServiceProvider";
    }
}
