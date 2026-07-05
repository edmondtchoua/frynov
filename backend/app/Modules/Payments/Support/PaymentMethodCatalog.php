<?php

namespace App\Modules\Payments\Support;

use InvalidArgumentException;

/**
 * Pont entre les moyens de paiement SPÉCIFIQUES affichés au checkout (market_payment_methods :
 * wave, orange_money, mtn_money, mpesa…) et la CATÉGORIE canonique stockée sur `Payment.method`
 * (cash/mobile_money/card/transfer/cheque).
 *
 * Permet au checkout commercial d'offrir un moyen précis par marché (P6) sans modifier l'enum
 * `Payment.method` : le provider est tracé (référence), la catégorie reste canonique.
 */
class PaymentMethodCatalog
{
    /** provider (market_payment_methods.method) => Payment.method (catégorie canonique). */
    public const PROVIDER_TO_CATEGORY = [
        'wave'          => 'mobile_money',
        'orange_money'  => 'mobile_money',
        'mtn_money'     => 'mobile_money',
        'mpesa'         => 'mobile_money',
        'bank_transfer' => 'transfer',
        'card'          => 'card',
        'cash'          => 'cash',
    ];

    public static function categoryFor(string $provider): string
    {
        return self::PROVIDER_TO_CATEGORY[$provider]
            ?? throw new InvalidArgumentException("Moyen de paiement inconnu : {$provider}");
    }

    /** @return array<int,string> */
    public static function providers(): array
    {
        return array_keys(self::PROVIDER_TO_CATEGORY);
    }
}
