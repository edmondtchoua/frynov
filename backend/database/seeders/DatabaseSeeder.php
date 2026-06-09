<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            // ── 1. Référentiel (idempotents — toujours exécutés) ────────────────
            RolesAndPermissionsSeeder::class, // rôles Spatie + permissions (admin/manager/member/viewer/agent/cashier/commercial/delivery)
            PlansSeeder::class,               // starter / pro / enterprise (avec quotas terrain: agents, branches, warehouses)
            MarketPaymentMethodsSeeder::class, // P6-1: moyens de paiement par marché (manual/quote, zéro PSP)
            ErpModulesSeeder::class,          // 10 modules ERP
            PlanModulesSeeder::class,         // associations plan ↔ modules
            CountryRulesSeeder::class,        // règles inscription par pays (30+ marchés africains + globaux)

            // ── 2. Super admin ─────────────────────────────────────────────────
            // superadmin@frynov.com / Secret123!
            SuperAdminSeeder::class,

            // ── 3. Données de démo (dev/staging uniquement) ────────────────────
            // 3 tenants couvrant tous les plans, rôles et fonctionnalités.
            // Désactivez ce seeder en production :
            //   php artisan db:seed --class=RolesAndPermissionsSeeder
            //   php artisan db:seed --class=PlansSeeder
            //   ...  (sans DemoSeeder)
            DemoSeeder::class,
        ]);
    }
}
