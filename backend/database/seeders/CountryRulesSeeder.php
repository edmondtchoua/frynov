<?php

namespace Database\Seeders;

use App\Modules\Auth\Models\CountryRule;
use Illuminate\Database\Seeder;

/**
 * Seeds default country rules for African and global markets.
 *
 * Rules:
 * - Most African markets: open registration with locale defaults
 * - Global markets (EU, US, CA): open registration, no default currency restriction
 * - Sanctioned/blocked: none by default (configurable by super-admin)
 */
class CountryRulesSeeder extends Seeder
{
    public function run(): void
    {
        $rules = [
            // ── Afrique de l'Ouest (UEMOA — XOF) ──────────────────────────────
            ['country_code' => 'SN', 'default_currency' => 'XOF', 'default_timezone' => 'Africa/Dakar',    'is_active' => true],
            ['country_code' => 'CI', 'default_currency' => 'XOF', 'default_timezone' => 'Africa/Abidjan',  'is_active' => true],
            ['country_code' => 'ML', 'default_currency' => 'XOF', 'default_timezone' => 'Africa/Bamako',   'is_active' => true],
            ['country_code' => 'BF', 'default_currency' => 'XOF', 'default_timezone' => 'Africa/Ouagadougou', 'is_active' => true],
            ['country_code' => 'GN', 'default_currency' => 'GNF', 'default_timezone' => 'Africa/Conakry',  'is_active' => true],
            ['country_code' => 'BJ', 'default_currency' => 'XOF', 'default_timezone' => 'Africa/Porto-Novo', 'is_active' => true],
            ['country_code' => 'TG', 'default_currency' => 'XOF', 'default_timezone' => 'Africa/Lome',     'is_active' => true],
            ['country_code' => 'NE', 'default_currency' => 'XOF', 'default_timezone' => 'Africa/Niamey',   'is_active' => true],

            // ── Afrique Centrale (CEMAC — XAF) ────────────────────────────────
            ['country_code' => 'CM', 'default_currency' => 'XAF', 'default_timezone' => 'Africa/Douala',   'is_active' => true],
            ['country_code' => 'CG', 'default_currency' => 'XAF', 'default_timezone' => 'Africa/Brazzaville', 'is_active' => true],
            ['country_code' => 'GA', 'default_currency' => 'XAF', 'default_timezone' => 'Africa/Libreville', 'is_active' => true],
            ['country_code' => 'TD', 'default_currency' => 'XAF', 'default_timezone' => 'Africa/Ndjamena', 'is_active' => true],

            // ── Afrique de l'Ouest anglophone ──────────────────────────────────
            ['country_code' => 'NG', 'default_currency' => 'NGN', 'default_timezone' => 'Africa/Lagos',    'is_active' => true],
            ['country_code' => 'GH', 'default_currency' => 'GHS', 'default_timezone' => 'Africa/Accra',    'is_active' => true],
            ['country_code' => 'SL', 'default_currency' => 'SLL', 'default_timezone' => 'Africa/Freetown', 'is_active' => true],
            ['country_code' => 'GM', 'default_currency' => 'GMD', 'default_timezone' => 'Africa/Banjul',   'is_active' => true],

            // ── Afrique du Nord ────────────────────────────────────────────────
            ['country_code' => 'MA', 'default_currency' => 'MAD', 'default_timezone' => 'Africa/Casablanca', 'is_active' => true],
            ['country_code' => 'TN', 'default_currency' => 'TND', 'default_timezone' => 'Africa/Tunis',    'is_active' => true],
            ['country_code' => 'DZ', 'default_currency' => 'DZD', 'default_timezone' => 'Africa/Algiers',  'is_active' => true],
            ['country_code' => 'EG', 'default_currency' => 'EGP', 'default_timezone' => 'Africa/Cairo',    'is_active' => true],

            // ── Afrique de l'Est ───────────────────────────────────────────────
            ['country_code' => 'KE', 'default_currency' => 'KES', 'default_timezone' => 'Africa/Nairobi',  'is_active' => true],
            ['country_code' => 'TZ', 'default_currency' => 'TZS', 'default_timezone' => 'Africa/Dar_es_Salaam', 'is_active' => true],
            ['country_code' => 'ET', 'default_currency' => 'ETB', 'default_timezone' => 'Africa/Addis_Ababa', 'is_active' => true],
            ['country_code' => 'UG', 'default_currency' => 'UGX', 'default_timezone' => 'Africa/Kampala',  'is_active' => true],

            // ── Afrique Australe ───────────────────────────────────────────────
            ['country_code' => 'ZA', 'default_currency' => 'ZAR', 'default_timezone' => 'Africa/Johannesburg', 'is_active' => true],
            ['country_code' => 'MZ', 'default_currency' => 'MZN', 'default_timezone' => 'Africa/Maputo',   'is_active' => true],

            // ── Europe ─────────────────────────────────────────────────────────
            ['country_code' => 'FR', 'default_currency' => 'EUR', 'default_timezone' => 'Europe/Paris',    'is_active' => true],
            ['country_code' => 'BE', 'default_currency' => 'EUR', 'default_timezone' => 'Europe/Brussels', 'is_active' => true],
            ['country_code' => 'CH', 'default_currency' => 'CHF', 'default_timezone' => 'Europe/Zurich',   'is_active' => true],

            // ── Amérique du Nord ───────────────────────────────────────────────
            ['country_code' => 'US', 'default_currency' => 'USD', 'default_timezone' => 'America/New_York', 'is_active' => true],
            ['country_code' => 'CA', 'default_currency' => 'CAD', 'default_timezone' => 'America/Toronto', 'is_active' => true],
        ];

        foreach ($rules as $rule) {
            CountryRule::firstOrCreate(
                ['country_code' => $rule['country_code']],
                array_merge($rule, [
                    'is_blocked'          => false,
                    'requires_approval'   => false,
                    'allowed_plans'       => null,
                    'metadata'            => null,
                ])
            );
        }

        $this->command->info('CountryRulesSeeder: ' . count($rules) . ' pays seedés.');
    }
}
