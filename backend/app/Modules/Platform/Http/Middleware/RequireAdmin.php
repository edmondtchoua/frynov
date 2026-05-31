<?php

namespace App\Modules\Platform\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->is_super_admin) {
            return response()->json(['message' => 'Accès réservé aux administrateurs.'], 403);
        }

        return $next($request);
    }
}
