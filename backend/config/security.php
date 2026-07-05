<?php

/**
 * RC-9 — réglages de sécurité transverses.
 */
return [

    // F-9 — plafond de requêtes GLOBAL par client (utilisateur ou IP) et par minute, sur `api/*`.
    // 0 = désactivé (utilisé en test). Les throttles serrés par route (auth, portail) restent en plus.
    'api_rate_limit' => (int) env('API_RATE_LIMIT', 600),

];
