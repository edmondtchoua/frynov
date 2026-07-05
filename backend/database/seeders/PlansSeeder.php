<?php

namespace Database\Seeders;

use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\PlanLimit;
use App\Modules\Billing\Models\PlanPrice;
use Illuminate\Database\Seeder;

class PlansSeeder extends Seeder
{
    private const MARKETS = [
        'waemu' => ['currency' => 'XOF', 'label' => 'UEMOA'],
        'cemac' => ['currency' => 'XAF', 'label' => 'CEMAC'],
        'nigeria' => ['currency' => 'NGN', 'label' => 'Nigeria'],
        'ghana' => ['currency' => 'GHS', 'label' => 'Ghana'],
        'kenya' => ['currency' => 'KES', 'label' => 'Kenya'],
        'south_africa' => ['currency' => 'ZAR', 'label' => 'South Africa'],
        'europe' => ['currency' => 'EUR', 'label' => 'Europe'],
        'canada' => ['currency' => 'CAD', 'label' => 'Canada'],
        'usa' => ['currency' => 'USD', 'label' => 'USA'],
        'global' => ['currency' => 'USD', 'label' => 'International'],
    ];

    public function run(): void
    {
        $plans = [
            Plan::CODE_STARTER => [
                'name' => 'Découverte',
                'description' => 'Essayez Frynov avec tous les modules métier et des volumes limités.',
                'legacy_price_monthly_cents' => 0,
                'legacy_currency' => 'XOF',
                'included_users' => 1,
                'extra_user_amounts' => ['XOF' => 0, 'XAF' => 0, 'NGN' => 0, 'GHS' => 0, 'KES' => 0, 'ZAR' => 0, 'EUR' => 0, 'CAD' => 0, 'USD' => 0],
                'prices' => ['XOF' => 0, 'XAF' => 0, 'NGN' => 0, 'GHS' => 0, 'KES' => 0, 'ZAR' => 0, 'EUR' => 0, 'CAD' => 0, 'USD' => 0],
                'limits' => ['max_products' => 100, 'max_monthly_orders' => 50, 'max_customers' => 100, 'max_branches' => 1, 'max_warehouses' => 1, 'max_imports_per_month' => 1, 'max_api_calls_per_month' => 0, 'storage_mb' => 250],
                'features' => ['Tous les modules métier visibles', '1 utilisateur inclus', '100 produits', '50 commandes/mois', 'Support communauté'],
                'trial_days' => 14,
                'is_public' => true,
                'sort_order' => 1,
            ],
            Plan::CODE_ESSENTIAL => [
                'name' => 'Essentiel',
                'description' => 'Pour gérer une boutique active avec tous les modules du quotidien.',
                'legacy_price_monthly_cents' => 990000,
                'legacy_currency' => 'XOF',
                'included_users' => 2,
                'extra_user_amounts' => ['XOF' => 250000, 'XAF' => 250000, 'NGN' => 300000, 'GHS' => 3000, 'KES' => 50000, 'ZAR' => 7000, 'EUR' => 700, 'CAD' => 1000, 'USD' => 700],
                'prices' => ['XOF' => 990000, 'XAF' => 990000, 'NGN' => 1500000, 'GHS' => 15000, 'KES' => 250000, 'ZAR' => 34900, 'EUR' => 1900, 'CAD' => 2500, 'USD' => 1900],
                'limits' => ['max_products' => 500, 'max_monthly_orders' => 300, 'max_customers' => 1000, 'max_branches' => 1, 'max_warehouses' => 1, 'max_imports_per_month' => 5, 'max_api_calls_per_month' => 0, 'storage_mb' => 1024],
                'features' => ['Tous les modules métier', '2 utilisateurs inclus', '500 produits', '300 commandes/mois', 'Paiements et livraisons', 'Support email'],
                'trial_days' => 14,
                'is_public' => true,
                'sort_order' => 2,
            ],
            Plan::CODE_PRO => [
                'name' => 'Croissance',
                'description' => 'Pour les équipes qui vendent plus, automatisent et suivent leurs performances.',
                'legacy_price_monthly_cents' => 2490000,
                'legacy_currency' => 'XOF',
                'included_users' => 5,
                'extra_user_amounts' => ['XOF' => 350000, 'XAF' => 350000, 'NGN' => 500000, 'GHS' => 5000, 'KES' => 80000, 'ZAR' => 12000, 'EUR' => 1200, 'CAD' => 1600, 'USD' => 1200],
                'prices' => ['XOF' => 2490000, 'XAF' => 2490000, 'NGN' => 3900000, 'GHS' => 39000, 'KES' => 650000, 'ZAR' => 89900, 'EUR' => 4900, 'CAD' => 6500, 'USD' => 4900],
                'limits' => ['max_products' => 5000, 'max_monthly_orders' => 2000, 'max_customers' => 10000, 'max_branches' => 3, 'max_warehouses' => 3, 'max_imports_per_month' => 25, 'max_api_calls_per_month' => 10000, 'storage_mb' => 10240],
                'features' => ['Tous les modules métier', '5 utilisateurs inclus', '5 000 produits', '2 000 commandes/mois', 'Rapports avancés', 'Marketplace', 'Support prioritaire'],
                'trial_days' => 14,
                'is_public' => true,
                'sort_order' => 3,
            ],
            Plan::CODE_ENTERPRISE => [
                'name' => 'Business / Enterprise',
                'description' => 'Pour les groupes, grossistes, franchises et opérations multi-sites.',
                'legacy_price_monthly_cents' => 5990000,
                'legacy_currency' => 'XOF',
                'included_users' => 10,
                'extra_user_amounts' => ['XOF' => 500000, 'XAF' => 500000, 'NGN' => 700000, 'GHS' => 7000, 'KES' => 120000, 'ZAR' => 18000, 'EUR' => 1800, 'CAD' => 2400, 'USD' => 1800],
                'prices' => ['XOF' => 5990000, 'XAF' => 5990000, 'NGN' => 9900000, 'GHS' => 99000, 'KES' => 1690000, 'ZAR' => 239900, 'EUR' => 12900, 'CAD' => 16900, 'USD' => 12900],
                'limits' => ['max_products' => null, 'max_monthly_orders' => null, 'max_customers' => null, 'max_branches' => null, 'max_warehouses' => null, 'max_imports_per_month' => null, 'max_api_calls_per_month' => null, 'storage_mb' => null],
                'features' => ['Tous les modules métier', '10 utilisateurs inclus', 'Volumes élevés ou sur devis', 'API & intégrations', 'SLA & support dédié', 'Formation et onboarding'],
                'trial_days' => 30,
                'is_public' => true,
                'sort_order' => 4,
            ],
        ];

        foreach ($plans as $code => $data) {
            // Seats are a SOFT "included" guideline on paid plans — exposed via
            // plan_prices.included_users for display (and future per-seat overage
            // billing) — NOT a hard cap. A growing business must never be blocked
            // from inviting members (the Business plan otherwise dead-ended at 10
            // with a misleading "upgrade" message). Only the free Découverte tier
            // keeps a hard user cap to nudge the upgrade to a paid plan.
            $userCap = $data['legacy_price_monthly_cents'] === 0 ? $data['included_users'] : null;

            $plan = Plan::updateOrCreate(['code' => $code], [
                'name' => $data['name'],
                'description' => $data['description'],
                'price_monthly_cents' => $data['legacy_price_monthly_cents'],
                'price_yearly_cents' => $data['legacy_price_monthly_cents'] * 10,
                'currency' => $data['legacy_currency'],
                'max_users' => $userCap,
                'max_products' => $data['limits']['max_products'],
                'max_monthly_orders' => $data['limits']['max_monthly_orders'],
                'max_agents' => $userCap,
                'max_branches' => $data['limits']['max_branches'],
                'max_warehouses' => $data['limits']['max_warehouses'],
                'trial_days' => $data['trial_days'],
                'features' => $data['features'],
                'is_active' => true,
                'is_public' => $data['is_public'],
                'sort_order' => $data['sort_order'],
            ]);

            PlanLimit::updateOrCreate(['plan_id' => $plan->id], $data['limits']);

            foreach (self::MARKETS as $marketCode => $market) {
                $currency = $market['currency'];
                $monthly  = $data['prices'][$currency];
                $extra    = $data['extra_user_amounts'][$currency];

                PlanPrice::updateOrCreate(
                    ['plan_id' => $plan->id, 'market_code' => $marketCode, 'interval' => 'monthly'],
                    [
                        'country_code' => null,
                        'currency' => $currency,
                        'base_amount_minor' => $monthly,
                        'included_users' => $data['included_users'],
                        'extra_user_amount_minor' => $extra,
                        'is_public' => true,
                        'sort_order' => $data['sort_order'],
                    ],
                );

                // Prix ANNUEL = 10× le mensuel (≈ 2 mois offerts, convention legacy). 0 reste 0 (gratuit).
                PlanPrice::updateOrCreate(
                    ['plan_id' => $plan->id, 'market_code' => $marketCode, 'interval' => 'yearly'],
                    [
                        'country_code' => null,
                        'currency' => $currency,
                        'base_amount_minor' => $monthly * 10,
                        'included_users' => $data['included_users'],
                        'extra_user_amount_minor' => $extra !== null ? $extra * 10 : null,
                        'is_public' => true,
                        'sort_order' => $data['sort_order'],
                    ],
                );
            }
        }

        $this->command->info('Plans seeded: Découverte, Essentiel, Croissance, Business / Enterprise with localized prices.');
    }
}
