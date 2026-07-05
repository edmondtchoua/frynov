<?php

/**
 * RC-9 F-10 — CORS restreint (au lieu du défaut `*`).
 *
 * L'API est consommée par jeton Bearer (pas de cookies de session cross-site), donc
 * `supports_credentials = false`. Les origines autorisées sont pilotées par `CORS_ALLOWED_ORIGINS`
 * (liste séparée par des virgules) ; défaut = origines de dev locales. En prod, renseigner le domaine
 * du front (ex. `https://app.frynov.com`).
 */
$origins = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) env('CORS_ALLOWED_ORIGINS', 'http://localhost:5173,http://localhost:3000,http://127.0.0.1:5173')),
)));

return [

    'paths' => ['api/*'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => $origins,

    'allowed_origins_patterns' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('CORS_ALLOWED_ORIGIN_PATTERNS', '')),
    ))),

    'allowed_headers' => ['Accept', 'Authorization', 'Content-Type', 'X-Requested-With', 'X-Tenant-Id', 'X-Tenant-Slug'],

    'exposed_headers' => ['X-RateLimit-Limit', 'X-RateLimit-Remaining', 'Retry-After'],

    'max_age' => 3600,

    'supports_credentials' => false,

];
