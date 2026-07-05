<?php

namespace App\Modules\Auth\Http\Middleware;

use App\Modules\Auth\Services\TenantResolverService;
use Closure;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenant
{
    public function __construct(
        private readonly TenantResolverService $resolver,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->resolver->resolve($request);

        if ($tenant) {
            app()->instance('current.tenant', $tenant);
            $request->attributes->set('tenant', $tenant);

            // Scope Spatie permissions to this tenant
            app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        }

        return $next($request);
    }
}
