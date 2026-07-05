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

    /*
     * RC-9 F-7 — sécurité des uploads de fichiers digitaux (assets servis aux clients finaux).
     * Liste blanche d'extensions (les types rendus « inline » dangereux — html/svg/js/xml… — sont
     * volontairement exclus ; le téléchargement force de toute façon une pièce jointe). Extensible
     * par config sans code.
     */
    'upload' => [
        'max_size_kb' => (int) env('DIGITAL_UPLOAD_MAX_KB', 51200), // 50 Mo
        'allowed_extensions' => [
            // documents
            'pdf', 'epub', 'mobi', 'azw3', 'doc', 'docx', 'odt', 'rtf', 'txt', 'md', 'csv',
            'xls', 'xlsx', 'ods', 'ppt', 'pptx', 'odp',
            // archives
            'zip', 'rar', '7z', 'tar', 'gz',
            // audio / vidéo
            'mp3', 'wav', 'flac', 'aac', 'ogg', 'm4a', 'mp4', 'mov', 'avi', 'mkv', 'webm',
            // images
            'png', 'jpg', 'jpeg', 'gif', 'webp', 'psd',
            // logiciels / installeurs (servis en pièce jointe, jamais rendus)
            'apk', 'exe', 'dmg', 'msi', 'iso',
            // licences / données
            'key', 'lic', 'dat',
        ],
    ],
];
