<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Mode d'attribution des accès démo
    |--------------------------------------------------------------------------
    | 'manual' : une demande attend l'approbation d'un admin (back-office) avant
    |            provisioning + envoi des accès. Recommandé au lancement.
    | 'auto'   : accès provisionné et envoyé immédiatement après soumission du
    |            formulaire (protégé par throttle + honeypot).
    */
    'mode' => env('DEMO_MODE', 'manual'),

    /*
    | Durée de validité d'un accès démo (jours). Au-delà, le tenant éphémère est
    | détruit et l'accès révoqué par la commande onboarding:revoke-expired-demos.
    */
    'access_ttl_days' => (int) env('DEMO_ACCESS_TTL_DAYS', 14),

    /*
    | Adresse interne notifiée à chaque nouvelle demande de démo.
    */
    'internal_notification_email' => env('DEMO_TEAM_EMAIL', env('MAIL_FROM_ADDRESS', 'contact@frynov.com')),

    /*
    | Base URL du frontend pour construire le lien de connexion démo.
    */
    'app_url' => rtrim(env('DEMO_APP_URL', env('FRONTEND_URL', 'http://localhost:5173')), '/'),

    /*
    | Throttle du formulaire public : "maxAttempts,decayMinutes".
    */
    'form_throttle' => env('DEMO_FORM_THROTTLE', '5,1'),

    /*
    | Envoyer un rappel N jours avant expiration (0 = désactivé).
    */
    'reminder_days_before' => (int) env('DEMO_REMINDER_DAYS_BEFORE', 3),
];
