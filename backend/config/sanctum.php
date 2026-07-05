<?php

use Laravel\Sanctum\Sanctum;

return [

    /*
    |--------------------------------------------------------------------------
    | Stateful Domains
    |--------------------------------------------------------------------------
    */
    'stateful' => explode(',', (string) env('SANCTUM_STATEFUL_DOMAINS', sprintf(
        '%s%s',
        'localhost,localhost:3000,127.0.0.1,127.0.0.1:8000,::1',
        Sanctum::currentApplicationUrlWithPort(),
    ))),

    'guard' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Expiration Minutes — filet de sécurité (recette QA F-1)
    |--------------------------------------------------------------------------
    | Aucun token ne doit vivre indéfiniment, même émis sans expiration explicite.
    | Les tokens applicatifs (users, 30 j) et portail (30 j) restent bornés par leur
    | propre `expiresAt` — l'échéance la plus proche l'emporte. Ce plafond global (défaut
    | 1 an) rattrape tout token émis sans `expiresAt` (ex. anciens tokens portail).
    | Une valeur nulle/0 est refusée (retombe sur 1 an) pour éviter une expiration immédiate.
    */
    'expiration' => ((int) env('SANCTUM_TOKEN_EXPIRATION_MINUTES', 60 * 24 * 365)) ?: 60 * 24 * 365,

    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),

    'middleware' => [
        'authenticate_session' => Laravel\Sanctum\Http\Middleware\AuthenticateSession::class,
        'encrypt_cookies' => Illuminate\Cookie\Middleware\EncryptCookies::class,
        'validate_csrf_token' => Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    ],

];
