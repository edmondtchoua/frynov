<?php

namespace App\Modules\Inventory\Support;

/**
 * RC-5B — normalisation déterministe des identifiants sérialisés, AVANT comparaison d'unicité et
 * recherche. La normalisation dépend du type : un IMEI saisi « 35-9123/45.678901-2 3 » et « 359123456789012 »
 * doivent être considérés identiques. Les types inconnus retombent sur un trim + majuscules sûr.
 */
final class SerialNormalizer
{
    public static function normalize(string $type, string $value): string
    {
        $type  = strtolower(trim($type));
        $value = trim($value);

        return match ($type) {
            'imei'   => preg_replace('/\D/', '', $value) ?? '',                       // chiffres uniquement
            'vin'    => strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $value)), // alphanumérique majuscules
            default  => strtoupper((string) preg_replace('/\s+/', '', $value)),       // sans espaces, majuscules
        };
    }
}
