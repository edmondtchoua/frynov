<?php

use App\Providers\AppServiceProvider;
use App\Modules\Tenants\Providers\TenantsServiceProvider;

return [
    AppServiceProvider::class,

    // ── Modules ERP ──────────────────────────────────
    TenantsServiceProvider::class,
    App\Modules\Auth\Providers\AuthServiceProvider::class,
    // Les autres modules seront ajoutés ici automatiquement
    // par `php artisan make:module`
    App\Modules\Orders\Providers\OrdersServiceProvider::class,
    App\Modules\Catalog\Providers\CatalogServiceProvider::class,
    App\Modules\Inventory\Providers\InventoryServiceProvider::class,
    App\Modules\Payments\Providers\PaymentsServiceProvider::class,
    App\Modules\Delivery\Providers\DeliveryServiceProvider::class,
    App\Modules\Customers\Providers\CustomersServiceProvider::class,
    App\Modules\Sync\Providers\SyncServiceProvider::class,
];
