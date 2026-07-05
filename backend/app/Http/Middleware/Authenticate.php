<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

/**
 * Custom Authenticate middleware.
 *
 * Override to ensure API routes never redirect to a 'login' named route
 * (which does not exist in this API-only application).
 * Unauthenticated API requests always receive a 401 JSON response.
 */
class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     * Returning null ensures a 401 JSON response is sent (no redirect).
     */
    protected function redirectTo(Request $request): ?string
    {
        // Never redirect for API routes — return null to trigger a 401 JSON response
        return null;
    }
}
