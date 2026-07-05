<?php

namespace App\Modules\Auth\Providers;

use App\Modules\Auth\Repositories\EloquentUserRepository;
use App\Modules\Auth\Repositories\UserRepositoryInterface;
use App\Shared\ModuleServiceProvider;

class AuthServiceProvider extends ModuleServiceProvider
{
    protected string $moduleName      = 'Auth';
    protected string $moduleNamespace = 'App\\Modules\\Auth';

    public function register(): void
    {
        $this->app->bind(UserRepositoryInterface::class, EloquentUserRepository::class);
    }

    public function boot(): void
    {
        parent::boot();
    }
}
