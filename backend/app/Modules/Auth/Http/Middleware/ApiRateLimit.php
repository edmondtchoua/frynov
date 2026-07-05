<?php

namespace App\Modules\Auth\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * RC-9 F-9 — garde-fou de débit GLOBAL sur l'API (anti-abus / DoS applicatif).
 *
 * Les routes d'auth et du portail ont déjà des throttles serrés par route ; ici on plafonne le VOLUME
 * global par client sur toutes les routes `api/*` (y compris celles des modules chargées hors du groupe
 * `api`, d'où un middleware GLOBAL et non un `throttle:api` de groupe). Clé = utilisateur authentifié
 * si résolu, sinon IP. Limite configurable (`security.api_rate_limit`, défaut 600/min).
 */
class ApiRateLimit
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->is('api/*')) {
            return $next($request);
        }

        $max = (int) config('security.api_rate_limit', 600);
        if ($max <= 0) {
            return $next($request); // désactivé explicitement
        }

        $key = 'api:' . ($request->user()?->getAuthIdentifier() ?? $request->ip());

        if (RateLimiter::tooManyAttempts($key, $max)) {
            $retry = RateLimiter::availableIn($key);

            return response()->json(
                ['message' => 'Trop de requêtes, réessayez dans un instant.'],
                429,
                ['Retry-After' => $retry, 'X-RateLimit-Limit' => $max],
            );
        }

        RateLimiter::hit($key, 60); // fenêtre 60 s

        $response = $next($request);
        $response->headers->set('X-RateLimit-Limit', (string) $max);
        $response->headers->set('X-RateLimit-Remaining', (string) RateLimiter::remaining($key, $max));

        return $response;
    }
}
