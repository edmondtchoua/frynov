<?php

namespace Database\Seeders;

use App\Modules\Billing\Models\Plan;
use Illuminate\Database\Seeder;

class PlansSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'code'                  => Plan::CODE_STARTER,
                'name'                  => 'Starter',
                'description'           => 'Idéal pour démarrer — gratuit sans carte bancaire.',
                'price_monthly_cents'   => 0,
                'price_yearly_cents'    => 0,
                'currency'              => 'XOF',
                'max_users'             => 3,
                'max_products'          => 200,
                'max_monthly_orders'    => 100,
                'max_agents'            => 3,
                'max_branches'          => 1,
                'max_warehouses'        => 1,
                'trial_days'            => 14,
                'features'              => [
                    'Tableau de bord',
                    'Catalogue produits (200 max)',
                    'Gestion des commandes (100/mois)',
                    'Inventaire de base',
                    'Support par email',
                ],
                'is_active'             => true,
                'is_public'             => true,
                'sort_order'            => 1,
            ],
            [
                'code'                  => Plan::CODE_PRO,
                'name'                  => 'Pro',
                'description'           => 'Pour les entreprises en croissance avec des besoins avancés.',
                'price_monthly_cents'   => 1500000,   // 15 000 XOF / month
                'price_yearly_cents'    => 15000000,  // 150 000 XOF / year (~17% off)
                'currency'              => 'XOF',
                'max_users'             => 15,
                'max_products'          => 5000,
                'max_monthly_orders'    => 2000,
                'max_agents'            => 10,
                'max_branches'          => 3,
                'max_warehouses'        => 3,
                'trial_days'            => 14,
                'features'              => [
                    'Tout le plan Starter',
                    'Catalogue illimité (5 000 produits)',
                    'Gestion des commandes (2 000/mois)',
                    'Gestion des clients avancée',
                    'Paiements & livraisons',
                    'Fournisseurs',
                    'Import/Export Excel & CSV',
                    'Rapports avancés',
                    'Support prioritaire',
                ],
                'is_active'             => true,
                'is_public'             => true,
                'sort_order'            => 2,
            ],
            [
                'code'                  => Plan::CODE_ENTERPRISE,
                'name'                  => 'Enterprise',
                'description'           => 'Solution sur-mesure pour les grandes structures.',
                'price_monthly_cents'   => 0,         // custom pricing
                'price_yearly_cents'    => 0,
                'currency'              => 'XOF',
                'max_users'             => 0,          // 0 = unlimited
                'max_products'          => 0,
                'max_monthly_orders'    => 0,
                'max_agents'            => null,       // unlimited
                'max_branches'          => null,
                'max_warehouses'        => null,
                'trial_days'            => 30,
                'features'              => [
                    'Tout le plan Pro',
                    'Utilisateurs illimités',
                    'Multi-entrepôts',
                    'API & intégrations',
                    'Tableau de bord personnalisé',
                    'SLA & support dédié',
                    'Formation et onboarding',
                    'Tarification sur devis',
                ],
                'is_active'             => true,
                'is_public'             => false,     // only shown when sales contacts them
                'sort_order'            => 3,
            ],
        ];

        foreach ($plans as $data) {
            Plan::updateOrCreate(['code' => $data['code']], $data);
        }

        $this->command->info('Plans seeded: starter (free), pro (15 000 XOF/month), enterprise (custom).');
    }
}
