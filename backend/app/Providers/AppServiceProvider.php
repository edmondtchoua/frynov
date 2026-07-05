<?php

namespace App\Providers;

use Illuminate\Database\Schema\Builder;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
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
    }
}
