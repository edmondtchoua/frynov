<?php

namespace Database\Seeders;

use App\Modules\Billing\Models\Plan;
use App\Modules\Platform\Models\ErpModule;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PlanModulesSeeder extends Seeder
{
    /**
     * Map plan code → included module codes (is_included = true).
     * Modules not listed are still linked but with is_included = false (add-on possible).
     */
    private const PLAN_MODULES = [
        Plan::CODE_STARTER => [
            'dashboard',
            'catalog',
            'inventory',
            'orders',
        ],
        Plan::CODE_PRO => [
            'dashboard',
            'catalog',
            'inventory',
            'orders',
            'customers',
            'payments',
            'delivery',
            'suppliers',
            'import_export',
            'reports',
        ],
        Plan::CODE_ENTERPRISE => [
            'dashboard',
            'catalog',
            'inventory',
            'orders',
            'customers',
            'payments',
            'delivery',
            'suppliers',
            'import_export',
            'reports',
        ],
    ];

    public function run(): void
    {
        $allModules = ErpModule::pluck('id', 'code');

        foreach (self::PLAN_MODULES as $planCode => $includedCodes) {
            $plan = Plan::where('code', $planCode)->first();
            if (! $plan) continue;

            // Remove old pivot rows for this plan
            DB::table('plan_modules')->where('plan_id', $plan->id)->delete();

            foreach ($allModules as $moduleCode => $moduleId) {
                $isIncluded = in_array($moduleCode, $includedCodes);

                DB::table('plan_modules')->insert([
                    'plan_id'     => $plan->id,
                    'module_id'   => $moduleId,
                    'is_included' => $isIncluded,
                    'limits'      => null,
                ]);
            }
        }

        $this->command->info('Plan–module associations seeded.');
    }
}
