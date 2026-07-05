<?php

namespace Database\Seeders;

use App\Modules\Billing\Models\Plan;
use App\Modules\Platform\Models\ErpModule;
use Illuminate\Database\Seeder;

class ErpModulesSeeder extends Seeder
{
    public function run(): void
    {
        $modules = [
            // ── Core (always active for every tenant) ─────────────────────────
            [
                'code'         => 'dashboard',
                'name'         => 'Tableau de bord',
                'category'     => ErpModule::CATEGORY_CORE,
                'description'  => 'Vue d\'ensemble des KPIs, activité récente et alertes.',
                'icon_svg'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>',
                'status'       => ErpModule::STATUS_ACTIVE,
                'is_core'      => true,
                'is_visible'   => true,
                'route_prefix' => '/dashboard',
                'color'        => '#4F6BED',
                'sort_order'   => 1,
            ],
            // ── Operations ────────────────────────────────────────────────────
            [
                'code'         => 'catalog',
                'name'         => 'Catalogue produits',
                'category'     => ErpModule::CATEGORY_OPERATIONS,
                'description'  => 'Gérez vos produits, variantes, catégories et prix.',
                'icon_svg'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 7H4a2 2 0 00-2 2v10a2 2 0 002 2h16a2 2 0 002-2V9a2 2 0 00-2-2z"/><path d="M16 7V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v2"/></svg>',
                'status'       => ErpModule::STATUS_ACTIVE,
                'is_core'      => false,
                'is_visible'   => true,
                'route_prefix' => '/catalog',
                'color'        => '#10B981',
                'sort_order'   => 2,
            ],
            [
                'code'         => 'inventory',
                'name'         => 'Inventaire',
                'category'     => ErpModule::CATEGORY_OPERATIONS,
                'description'  => 'Stocks, mouvements, alertes de rupture et valorisation.',
                'icon_svg'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/></svg>',
                'status'       => ErpModule::STATUS_ACTIVE,
                'is_core'      => false,
                'is_visible'   => true,
                'route_prefix' => '/inventory',
                'color'        => '#F59E0B',
                'sort_order'   => 3,
            ],
            [
                'code'         => 'orders',
                'name'         => 'Commandes',
                'category'     => ErpModule::CATEGORY_OPERATIONS,
                'description'  => 'Cycle de vie complet des commandes, de la création à la livraison.',
                'icon_svg'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="2"/><path d="M9 12h6M9 16h4"/></svg>',
                'status'       => ErpModule::STATUS_ACTIVE,
                'is_core'      => false,
                'is_visible'   => true,
                'route_prefix' => '/orders',
                'color'        => '#3B82F6',
                'sort_order'   => 4,
            ],
            [
                'code'         => 'customers',
                'name'         => 'Clients',
                'category'     => ErpModule::CATEGORY_OPERATIONS,
                'description'  => 'CRM léger : profils clients, historique et segmentation.',
                'icon_svg'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>',
                'status'       => ErpModule::STATUS_ACTIVE,
                'is_core'      => false,
                'is_visible'   => true,
                'route_prefix' => '/customers',
                'color'        => '#8B5CF6',
                'sort_order'   => 5,
            ],
            // ── Finance ───────────────────────────────────────────────────────
            [
                'code'         => 'payments',
                'name'         => 'Paiements',
                'category'     => ErpModule::CATEGORY_FINANCE,
                'description'  => 'Suivi des paiements, Mobile Money, virement et espèces.',
                'icon_svg'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2"/><path d="M1 10h22"/></svg>',
                'status'       => ErpModule::STATUS_ACTIVE,
                'is_core'      => false,
                'is_visible'   => true,
                'route_prefix' => '/payments',
                'color'        => '#EF4444',
                'sort_order'   => 6,
            ],
            [
                'code'         => 'delivery',
                'name'         => 'Livraisons',
                'category'     => ErpModule::CATEGORY_OPERATIONS,
                'description'  => 'Gestion des expéditions, transporteurs et suivi.',
                'icon_svg'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13"/><path d="M16 8h4l3 3v5h-7V8z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>',
                'status'       => ErpModule::STATUS_ACTIVE,
                'is_core'      => false,
                'is_visible'   => true,
                'route_prefix' => '/delivery',
                'color'        => '#06B6D4',
                'sort_order'   => 7,
            ],
            [
                'code'         => 'suppliers',
                'name'         => 'Fournisseurs',
                'category'     => ErpModule::CATEGORY_OPERATIONS,
                'description'  => 'Répertoire fournisseurs, commandes et approvisionnement.',
                'icon_svg'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>',
                'status'       => ErpModule::STATUS_ACTIVE,
                'is_core'      => false,
                'is_visible'   => true,
                'route_prefix' => '/suppliers',
                'color'        => '#F97316',
                'sort_order'   => 8,
            ],
            // ── Advanced ──────────────────────────────────────────────────────
            [
                'code'         => 'import_export',
                'name'         => 'Import / Export',
                'category'     => ErpModule::CATEGORY_ADVANCED,
                'description'  => 'Importation et exportation en masse via Excel et CSV.',
                'icon_svg'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>',
                'status'       => ErpModule::STATUS_ACTIVE,
                'is_core'      => false,
                'is_visible'   => true,
                'route_prefix' => '/import-export',
                'color'        => '#14B8A6',
                'sort_order'   => 9,
            ],
            // ── Analytics ─────────────────────────────────────────────────────
            [
                'code'         => 'reports',
                'name'         => 'Rapports',
                'category'     => ErpModule::CATEGORY_ANALYTICS,
                'description'  => 'Rapports de ventes, stock et tableaux de bord analytiques.',
                'icon_svg'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>',
                'status'       => ErpModule::STATUS_ACTIVE,
                'is_core'      => false,
                'is_visible'   => true,
                'route_prefix' => '/reports',
                'color'        => '#6366F1',
                'sort_order'   => 10,
            ],
        ];

        foreach ($modules as $data) {
            ErpModule::updateOrCreate(['code' => $data['code']], $data);
        }

        $this->command->info('ERP modules seeded: ' . count($modules) . ' modules.');
    }
}
