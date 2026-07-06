<?php

namespace App\Providers;

use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\Policies\CustomerPolicy;
use App\Modules\Delivery\Models\Delivery;
use App\Modules\Delivery\Policies\DeliveryPolicy;
use App\Modules\Suppliers\Models\Supplier;
use App\Modules\Suppliers\Policies\SupplierPolicy;
use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Audit RBAC P2 — politiques d'autorisation (2ᵉ ligne de défense côté contrôleur, en plus du
     * middleware de route). Auto-discovery ne trouve pas les policies modulaires → enregistrement explicite.
     */
    private const POLICIES = [
        Supplier::class => SupplierPolicy::class,
        Customer::class => CustomerPolicy::class,
        Delivery::class => DeliveryPolicy::class,
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // MySQL < 5.7.7 / MariaDB : utf8mb4 index length fix.
        // VARCHAR(255) × 4 bytes = 1020 > 1000-byte MySQL limit.
        // 191 × 4 = 764 bytes — stays within the limit on all versions.
        Builder::defaultStringLength(191);

        foreach (self::POLICIES as $model => $policy) {
            Gate::policy($model, $policy);
        }
    }
}
