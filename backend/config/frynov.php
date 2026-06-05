<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Module feature flags
    |--------------------------------------------------------------------------
    |
    | Toggles for modules that are scaffolded/tested but not ready for general
    | availability. Disabled flags keep the code and tests in the repo while
    | hiding the module's API routes from real tenants. Enable per-environment
    | via the matching env var.
    |
    */

    'modules' => [
        // Sync — Phase 3 (domaine métier non défini). Scaffold CRUD testé (33 tests)
        // mais masqué en production tant que le périmètre n'est pas arrêté.
        'sync' => (bool) env('FEATURE_SYNC', false),
    ],

];
