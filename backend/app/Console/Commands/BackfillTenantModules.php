<?php

namespace App\Console\Commands;

use App\Modules\Billing\Models\Plan;
use App\Modules\Platform\Models\ErpModule;
use App\Modules\Platform\Models\TenantModule;
use App\Modules\Platform\Services\ModuleRegistryService;
use App\Modules\Tenants\Models\Tenant;
use Illuminate\Console\Command;

/**
 * Backfill `tenant_modules` for tenants created BEFORE module gating became fail-closed
 * (security audit remediation, rc.5). Without this, an existing tenant with zero
 * tenant_modules rows is denied on EVERY business module after deploy.
 *
 * New tenants are already provisioned at subscription time
 * (SubscriptionService::activatePlanModules). This command brings legacy tenants to the
 * same baseline. Idempotent — a tenant that already has a row for every module is skipped.
 *
 *   php artisan tenants:backfill-modules            # apply
 *   php artisan tenants:backfill-modules --dry-run  # report only, no writes
 */
class BackfillTenantModules extends Command
{
    protected $signature = 'tenants:backfill-modules {--dry-run : Report what would change without writing}';

    protected $description = 'Activate plan modules for tenants missing tenant_modules rows (fail-closed migration)';

    public function handle(ModuleRegistryService $registry): int
    {
        $dryRun       = (bool) $this->option('dry-run');
        $allModuleIds = ErpModule::pluck('id');
        $total        = $allModuleIds->count();

        if ($total === 0) {
            $this->warn('Aucun module ERP en base — exécutez d’abord ErpModulesSeeder.');

            return self::FAILURE;
        }

        $provisioned = 0;
        $skipped     = 0;

        Tenant::query()->orderBy('created_at')->each(function (Tenant $tenant) use (
            $registry, $allModuleIds, $total, $dryRun, &$provisioned, &$skipped
        ) {
            $existing = TenantModule::where('tenant_id', $tenant->id)->count();

            // Already fully configured → don't touch (respects admin (de)activations).
            if ($existing >= $total) {
                $skipped++;

                return;
            }

            $this->line(($dryRun ? '[dry-run] ' : '') .
                "Provisionnement « {$tenant->name} » (plan: {$tenant->plan}, modules existants: {$existing}/{$total})");

            if ($dryRun) {
                $provisioned++;

                return;
            }

            $plan = Plan::where('code', $tenant->plan)->first();

            if ($plan && $plan->includedModules()->exists()) {
                // Same path as a real subscription: activate exactly the plan's modules.
                $registry->activatePlanModules($tenant, $plan);
            } else {
                // No resolvable plan / plan has no modules → activate every module so the
                // tenant is never locked out (product rule: modules available on every plan).
                foreach ($allModuleIds as $moduleId) {
                    TenantModule::updateOrCreate(
                        ['tenant_id' => $tenant->id, 'module_id' => $moduleId],
                        ['status' => TenantModule::STATUS_ACTIVE, 'activated_at' => now()],
                    );
                }
            }

            $provisioned++;
        });

        $this->info(($dryRun ? '[dry-run] ' : '') .
            "Tenants provisionnés : {$provisioned} · ignorés (déjà complets) : {$skipped}");

        return self::SUCCESS;
    }
}
