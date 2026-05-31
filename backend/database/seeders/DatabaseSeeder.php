<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            // 1. Roles & permissions (Spatie) — must run first, other seeders may assign roles
            RolesAndPermissionsSeeder::class,
            // 2. Billing plans
            PlansSeeder::class,
            // 3. ERP module registry
            ErpModulesSeeder::class,
            // 4. Plan ↔ module associations (depends on plans + modules)
            PlanModulesSeeder::class,
        ]);
    }
}
