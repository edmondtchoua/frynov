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

    /*
    |--------------------------------------------------------------------------
    | RC-6G (Phase 2G) — règles billing reportées de la revue RC-1/RC-2
    |--------------------------------------------------------------------------
    | Arbitrage fondateur : « tout implémenter, mais configurable ». Chaque règle
    | est activable individuellement via .env, sans redéploiement de code.
    */
    'rules' => [
        // Avoirs dans la table dédiée `tenant_credits` (sinon metadata legacy).
        'tenant_credits_table'   => env('BILLING_TENANT_CREDITS', true),

        // Détection de périodicité : cible NETTE après promo (active automatiquement les
        // paiements portant un promo_code au lieu de les router en needs_review).
        'promo_net_target'       => env('BILLING_PROMO_NET_TARGET', true),

        // Sièges additionnels (`extra_user`) intégrés au matching montant↔prix.
        'extra_user_matching'    => env('BILLING_EXTRA_USER_MATCHING', true),

        // Devise du paiement incohérente avec le moyen de paiement → needs_review strict.
        'strict_currency_method' => env('BILLING_STRICT_CURRENCY_METHOD', true),

        // Acompte échelonné : abonder l'abonnement past_due EN PLACE (applyDeposit) au lieu
        // d'annuler/recréer une ligne d'abonnement par tranche.
        'apply_deposit_in_place' => env('BILLING_APPLY_DEPOSIT_IN_PLACE', true),

        // Rejet/remboursement d'un acompte déjà imputé → décrémente le cumul (rétro-action).
        'deposit_reversal'       => env('BILLING_DEPOSIT_REVERSAL', true),
    ],
];
