<?php

namespace App\Modules\Auth\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserBelongsToTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Non authentifié.'], 401);
        }

        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        $tenant = $request->attributes->get('tenant')
            ?? (app()->bound('current.tenant') ? app('current.tenant') : null);

        if ($tenant) {
            // Tenant resolved from request — verify the user belongs to it
            if ($user->tenant_id !== $tenant->id) {
                return response()->json(['message' => 'Accès non autorisé à ce tenant.'], 403);
            }
        } elseif ($user->tenant_id) {
            // No tenant header sent — scope to the user's own tenant (typical mobile/SPA session)
            $request->attributes->set('tenant', $user->tenant);
        } else {
            return response()->json(['message' => 'Tenant non identifié.'], 400);
        }

        return $next($request);
    }
}
