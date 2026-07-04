<?php

/**
 * RC-7E — crédits de communication rechargeables par tenant × canal.
 *
 * Chaque tenant dispose d'un solde prépayé par canal (email / SMS / WhatsApp). L'envoi d'une
 * notification sur un canal **facturé** décompte 1 crédit ; à zéro, l'envoi est bloqué (statut
 * `no_credit`) sans jamais interrompre le flux métier appelant. Le tenant recharge en achetant un
 * **pack** (rail de paiement manuel : l'opérateur confirme l'encaissement, puis applique la recharge).
 */
return [

    'credits' => [

        // Interrupteur global (arbitrage fondateur G : tout configurable). À false → aucun décompte,
        // tous les canaux envoient sans consommer de crédit (utile en recette / pour un tenant offert).
        'enabled' => env('NOTIFICATION_CREDITS_ENABLED', true),

        // Canaux facturés. Un canal absent de cette liste envoie sans décompte (ex. email offert).
        'metered_channels' => ['email', 'sms', 'whatsapp'],

        // Packs de recharge (rail de paiement manuel). `credits` = nombre d'envois ; `price_cents` =
        // montant à encaisser (mineur). Éditable sans code — un pack inconnu est rejeté (422).
        'packs' => [
            'email_10k' => ['channel' => 'email',    'credits' => 10000, 'price_cents' => 500000,  'currency' => 'XOF'],
            'sms_1k'    => ['channel' => 'sms',       'credits' => 1000,  'price_cents' => 1500000, 'currency' => 'XOF'],
            'sms_5k'    => ['channel' => 'sms',       'credits' => 5000,  'price_cents' => 6750000, 'currency' => 'XOF'],
            'wa_1k'     => ['channel' => 'whatsapp',  'credits' => 1000,  'price_cents' => 2000000, 'currency' => 'XOF'],
            'wa_5k'     => ['channel' => 'whatsapp',  'credits' => 5000,  'price_cents' => 9000000, 'currency' => 'XOF'],
        ],
    ],

    /*
     * Agrégateurs réels (SMS / WhatsApp / passerelle email HTTP) — presets de config du canal
     * `http_api`. Le transport générique (`NotificationService::deliverHttpApi`) rend {{to}}, {{message}},
     * {{from}}, {{from_name}}, {{subject}} dans l'URL, les en-têtes et le payload : brancher un
     * fournisseur = créer un canal `http_api` avec l'un de ces gabarits (aucune ligne de code).
     *
     * Exemples fournis à titre de documentation (l'opérateur renseigne sa clé API et son sender).
     */
    'aggregator_presets' => [
        // Termii (Afrique de l'Ouest) — SMS.
        'termii_sms' => [
            'provider' => 'http_api',
            'channel'  => 'sms',
            'config'   => [
                'url'     => 'https://api.ng.termii.com/api/sms/send',
                'method'  => 'POST',
                'headers' => ['Content-Type' => 'application/json'],
                'payload' => [
                    'to'      => '{{to}}',
                    'from'    => '{{from_name}}',
                    'sms'     => '{{message}}',
                    'type'    => 'plain',
                    'channel' => 'generic',
                    'api_key' => 'REMPLIR_VOTRE_CLE_API',
                ],
            ],
        ],
        // Orange SMS API (Sonatel / Orange Money markets) — SMS.
        'orange_sms' => [
            'provider' => 'http_api',
            'channel'  => 'sms',
            'config'   => [
                'url'     => 'https://api.orange.com/smsmessaging/v1/outbound/{{from}}/requests',
                'method'  => 'POST',
                'headers' => ['Authorization' => 'Bearer REMPLIR_VOTRE_TOKEN', 'Content-Type' => 'application/json'],
                'payload' => [
                    'outboundSMSMessageRequest' => [
                        'address'          => 'tel:{{to}}',
                        'senderAddress'    => 'tel:{{from}}',
                        'outboundSMSTextMessage' => ['message' => '{{message}}'],
                    ],
                ],
            ],
        ],
        // Meta WhatsApp Business Cloud API — WhatsApp.
        'whatsapp_cloud' => [
            'provider' => 'http_api',
            'channel'  => 'whatsapp',
            'config'   => [
                'url'     => 'https://graph.facebook.com/v20.0/REMPLIR_PHONE_NUMBER_ID/messages',
                'method'  => 'POST',
                'headers' => ['Authorization' => 'Bearer REMPLIR_VOTRE_TOKEN', 'Content-Type' => 'application/json'],
                'payload' => [
                    'messaging_product' => 'whatsapp',
                    'to'                => '{{to}}',
                    'type'              => 'text',
                    'text'              => ['body' => '{{message}}'],
                ],
            ],
        ],
    ],
];
