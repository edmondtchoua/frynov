# Système de modules

## Principe

Le backend est organisé en **modules fonctionnels** autonomes. Chaque module regroupe tout ce qui concerne un domaine métier : modèles, migrations, services, controllers, routes, tests et vues.

```
app/Modules/
├── Auth/
├── Catalog/
├── Inventory/       ← à venir
├── Orders/          ← à venir
├── Payments/        ← à venir
├── Customers/       ← à venir
├── Delivery/        ← à venir
└── Tenants/
```

---

## Structure d'un module

```
app/Modules/Catalog/
├── Http/
│   ├── Controllers/
│   │   ├── ProductController.php
│   │   ├── CategoryController.php
│   │   └── LabelController.php
│   └── Requests/
│       ├── CreateProductRequest.php
│       └── BatchLabelRequest.php
├── Models/
│   ├── Product.php
│   ├── Category.php
│   └── ProductVariant.php
├── Providers/
│   └── CatalogServiceProvider.php   ← point d'entrée du module
├── Services/
│   ├── CatalogService.php
│   ├── SkuGeneratorService.php
│   ├── ProductCodeService.php
│   └── LabelService.php
├── Events/
│   └── ProductArchived.php
├── Tests/
│   ├── Unit/
│   │   ├── CatalogServiceTest.php
│   │   └── LabelServiceTest.php
│   └── Integration/
│       ├── ProductApiTest.php
│       └── LabelApiTest.php
├── resources/
│   └── views/
│       └── labels/
│           ├── thermal.blade.php
│           └── a4sheet.blade.php
└── routes/
    └── api.php
```

---

## Classe de base `ModuleServiceProvider`

Tous les `ServiceProvider` de module héritent de `App\Shared\ModuleServiceProvider` qui fournit des helpers :

```php
abstract class ModuleServiceProvider extends ServiceProvider
{
    protected string $moduleName;       // ex: 'Catalog'
    protected string $moduleNamespace;  // ex: 'App\\Modules\\Catalog'

    // Retourne le chemin absolu vers un fichier du module
    protected function modulePath(string $path = ''): string

    // Enregistre les routes API sous middleware 'api' + préfixe 'api'
    protected function loadApiRoutes(string $path): void

    // boot() appelle automatiquement loadApiRoutes et loadMigrationsFrom
    public function boot(): void
}
```

### Exemple de ServiceProvider minimal

```php
namespace App\Modules\Inventory\Providers;

use App\Shared\ModuleServiceProvider;

class InventoryServiceProvider extends ModuleServiceProvider
{
    protected string $moduleName      = 'Inventory';
    protected string $moduleNamespace = 'App\\Modules\\Inventory';

    public function register(): void
    {
        $this->app->singleton(StockService::class, fn($app) =>
            new StockService($app->make(ProductRepository::class))
        );
    }
    // boot() hérité → charge routes/api.php et les migrations automatiquement
}
```

---

## Enregistrement des modules

Les modules sont enregistrés dans `bootstrap/providers.php` :

```php
return [
    App\Providers\AppServiceProvider::class,
    App\Modules\Auth\Providers\AuthServiceProvider::class,
    App\Modules\Catalog\Providers\CatalogServiceProvider::class,
    // App\Modules\Inventory\Providers\InventoryServiceProvider::class,
];
```

---

## Créer un nouveau module

```bash
php artisan make:module NomDuModule
```

Cette commande génère automatiquement la structure complète (stub) avec :
- `Providers/NomDuModuleServiceProvider.php`
- `Models/.gitkeep`, `Services/.gitkeep`, `Http/Controllers/.gitkeep`
- `Tests/Unit/.gitkeep`, `Tests/Integration/.gitkeep`
- `routes/api.php` (vide avec commentaire de structure)

---

## Namespace Blade des vues

Chaque module peut enregistrer ses vues sous un namespace `catalog::` :

```php
// Dans CatalogServiceProvider::boot()
$this->loadViewsFrom($this->modulePath('resources/views'), 'catalog');

// Utilisation dans le code
return view('catalog::labels.thermal', compact('labels', 'options', 'format'));
```
