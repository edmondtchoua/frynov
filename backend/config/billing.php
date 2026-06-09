<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Secrets de signature des webhooks PSP (P6-3)
    |--------------------------------------------------------------------------
    | HMAC-SHA256 par prestataire, lu par VerifyWebhookSignature. Vide => tout
    | webhook de ce prestataire est REFUSÉ (défense par défaut). À renseigner via
    | .env uniquement lorsqu'un PSP réel est branché (P6-4).
    */
    'webhooks' => [
        'flutterwave' => ['secret' => env('FLUTTERWAVE_WEBHOOK_SECRET', '')],
        'paystack'    => ['secret' => env('PAYSTACK_WEBHOOK_SECRET', '')],
        'stripe'      => ['secret' => env('STRIPE_WEBHOOK_SECRET', '')],
        'cinetpay'    => ['secret' => env('CINETPAY_WEBHOOK_SECRET', '')],
        'wave'        => ['secret' => env('WAVE_WEBHOOK_SECRET', '')],
    ],

    /*
    |--------------------------------------------------------------------------
    | Feature flag — rails PSP réels (P6-4)
    |--------------------------------------------------------------------------
    | FALSE par défaut : AUCUN encaissement automatisé (NO-GO commercial respecté).
    | Seul le rail 'manual' (approche A — preuve + validation admin) est disponible.
    | P6-4 passera ce flag à true, marché par marché, une fois les comptes marchands
    | ouverts et les secrets webhooks renseignés.
    */
    'gateways_enabled' => env('PAYMENT_GATEWAYS_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Flutterwave — adaptateur de référence (P6-4)
    |--------------------------------------------------------------------------
    | Couverture panafricaine (cartes + Mobile Money : Nigeria, Ghana, Kenya, UEMOA…).
    | INERTE tant que `gateways_enabled` est false OU que `secret_key` est vide.
    | Renseigner via .env pour activer. Vérifier le contrat contre la doc Flutterwave v3
    | avant production.
    */
    'flutterwave' => [
        'base_url'   => env('FLUTTERWAVE_BASE_URL', 'https://api.flutterwave.com'),
        'secret_key' => env('FLUTTERWAVE_SECRET_KEY', ''),
    ],
];
