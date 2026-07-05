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
        // New pricing strategy: modules are accessible on every public plan.
        // Plans now monetize seats and critical resource limits instead of hiding
        // entire workflows from small merchants.
        Plan::CODE_STARTER => '*',
        Plan::CODE_ESSENTIAL => '*',
        Plan::CODE_PRO => '*',
        Plan::CODE_ENTERPRISE => '*',
    ];

    public function run(): void
    {
        $allModules = ErpModule::pluck('id', 'code');

        foreach (self::PLAN_MODULES as $planCode => $includedCodes) {
            $plan = Plan::where('code', $planCode)->first();
            if (! $plan) {
                continue;
            }

            // Remove old pivot rows for this plan
            DB::table('plan_modules')->where('plan_id', $plan->id)->delete();

            foreach ($allModules as $moduleCode => $moduleId) {
                $isIncluded = $includedCodes === '*' || in_array($moduleCode, $includedCodes, true);

                DB::table('plan_modules')->insert([
                    'plan_id' => $plan->id,
                    'module_id' => $moduleId,
                    'is_included' => $isIncluded,
                    'limits' => null,
                ]);
            }
        }

        $this->command->info('Plan–module associations seeded.');
    }
}
