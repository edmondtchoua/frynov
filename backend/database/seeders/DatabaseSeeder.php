<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            // ── 1. Référentiel (idempotents — toujours exécutés) ────────────────
            RolesAndPermissionsSeeder::class, // rôles Spatie + permissions
            PlansSeeder::class,               // starter / pro / enterprise
            ErpModulesSeeder::class,          // 10 modules ERP
            PlanModulesSeeder::class,         // associations plan ↔ modules

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
