<?php

/**
 * RC-6E — politiques du pool de licences, VARIABLES SELON LE PLAN d'abonnement du tenant
 * (arbitrage fondateur E). Surcharge possible par tenant via `settings['license_pool_exhaustion']`.
 */
return [

    // Comportement quand le pool importé est épuisé au moment d'une vente :
    //   generate → repli sur la génération de clé à la volée (RC-5E) + alerte ;
    //   block    → la livraison échoue (la commande reste confirmée) tant que des clés ne sont pas importées.
    'pool_exhaustion' => [
        'default'  => 'generate',
        'per_plan' => [
            // ex. 'enterprise' => 'block',
        ],
    ],

    // Taille maximale d'un import de clés, par plan (défaut si plan absent).
    'pool_import_limits' => [
        'default'  => 100,
        'per_plan' => [
            'pro'        => 1000,
            'enterprise' => 5000,
        ],
    ],
];
