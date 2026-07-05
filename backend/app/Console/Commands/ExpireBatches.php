<?php

namespace App\Console\Commands;

use App\Modules\Inventory\Models\ProductBatch;
use App\Modules\Notifications\Services\NotificationService;
use App\Modules\Tenants\Models\Tenant;
use Illuminate\Console\Command;

/**
 * RC-7A (suite recette QA) — DÉMARQUE automatique : les lots `active` dont la DLC est passée basculent
 * en `expired` (ils étaient déjà exclus de la vente par RC-6H/QA ; ce job aligne le statut et alerte le
 * tenant pour retrait physique). Quotidien (cf. routes/console.php).
 */
class ExpireBatches extends Command
{
    protected $signature   = 'inventory:expire-batches';
    protected $description = 'Passe en expired les lots actifs dont la date de péremption est dépassée + alerte (RC-7A)';

    public function handle(NotificationService $notifications): int
    {
        $expired = ProductBatch::withoutTenantScope()
            ->where('status', ProductBatch::STATUS_ACTIVE)
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '<', now()->toDateString())
            ->get();

        $byTenant = $expired->groupBy('tenant_id');

        foreach ($byTenant as $tenantId => $batches) {
            ProductBatch::withoutTenantScope()
                ->whereIn('id', $batches->pluck('id'))
                ->update(['status' => ProductBatch::STATUS_EXPIRED]);

            // Alerte récapitulative (best-effort) au tenant : lots à retirer physiquement.
            try {
                $tenant = Tenant::withoutGlobalScopes()->find($tenantId);
                $recipient = (string) ($tenant->settings['billing_email']
                    ?? \App\Models\User::where('tenant_id', $tenantId)->orderBy('created_at')->value('email')
                    ?? '');
                if ($recipient !== '') {
                    $lines = $batches->map(fn (ProductBatch $b) => "- Lot {$b->batch_number} (qté {$b->quantity}, DLC {$b->expiry_date?->toDateString()})")->implode("\n");
                    $notifications->notify($tenantId, 'inventory.batches_expired', $recipient, [
                        'tenant_name' => $tenant->name,
                        'count'       => $batches->count(),
                        'lines'       => $lines,
                    ]);
                }
            } catch (\Throwable) {
                // best-effort
            }
        }

        $this->info("Lots périmés démarqués : {$expired->count()}");

        return self::SUCCESS;
    }
}
