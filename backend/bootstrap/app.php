<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->appendToGroup('api', \App\Modules\Auth\Http\Middleware\ResolveTenant::class);

        // Register named aliases for convenience in route definitions
        $middleware->alias([
            'webhook.signature' => \App\Modules\Billing\Http\Middleware\VerifyWebhookSignature::class,
            'quota'             => \App\Modules\Billing\Http\Middleware\EnforceQuota::class,
            // Sprint 11: Spatie Permission role/permission middleware
            'role'              => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission'        => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission'=> \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            // S1: alias for EnsureUserBelongsToTenant (shorter form used in route files)
            'tenant'            => \App\Modules\Auth\Http\Middleware\EnsureUserBelongsToTenant::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
