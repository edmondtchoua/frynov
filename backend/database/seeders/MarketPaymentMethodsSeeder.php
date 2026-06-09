<?php

namespace Database\Seeders;

use App\Modules\Billing\Models\MarketPaymentMethod;
use Illuminate\Database\Seeder;

/**
 * P6-1 — moyens de paiement par marché.
 *
 * TOUT en `manual` (preuve + validation admin via ManualPayment) ou `quote` (sur devis) —
 * AUCUN rail PSP réel à ce stade (NO-GO commercial respecté). Le passage d'un marché en
 * `auto` se fera lors de l'intégration d'un PSP réel (P6-4), marché par marché.
 *
 * Aligné sur les 10 marchés de PublicPricingController::MARKETS (mêmes codes/devises) afin de
 * ne PAS créer une source de vérité divergente. Idempotent (updateOrCreate).
 */
class MarketPaymentMethodsSeeder extends Seeder
{
    /** marché => [devise, [[method, mode], …]] */
    private const SEED = [
        'waemu'        => ['XOF', [['orange_money', 'manual'], ['wave', 'manual'], ['mtn_money', 'manual'], ['bank_transfer', 'manual'], ['card', 'quote']]],
        'cemac'        => ['XAF', [['orange_money', 'manual'], ['mtn_money', 'manual'], ['bank_transfer', 'manual'], ['card', 'quote']]],
        'nigeria'      => ['NGN', [['bank_transfer', 'manual'], ['card', 'quote']]],
        'ghana'        => ['GHS', [['mtn_money', 'manual'], ['bank_transfer', 'manual'], ['card', 'quote']]],
        'kenya'        => ['KES', [['mpesa', 'manual'], ['bank_transfer', 'manual'], ['card', 'quote']]],
        'south_africa' => ['ZAR', [['bank_transfer', 'manual'], ['card', 'quote']]],
        'europe'       => ['EUR', [['card', 'quote'], ['bank_transfer', 'manual']]],
        'canada'       => ['CAD', [['card', 'quote'], ['bank_transfer', 'manual']]],
        'usa'          => ['USD', [['card', 'quote'], ['bank_transfer', 'manual']]],
        'global'       => ['USD', [['bank_transfer', 'manual'], ['card', 'quote']]],
    ];

    public function run(): void
    {
        foreach (self::SEED as $market => [$currency, $methods]) {
            foreach ($methods as $i => [$method, $mode]) {
                MarketPaymentMethod::updateOrCreate(
                    ['market_code' => $market, 'country_code' => null, 'method' => $method],
                    ['currency' => $currency, 'mode' => $mode, 'is_active' => true, 'display_order' => $i],
                );
            }
        }
    }
}
