<?php
namespace App\Modules\Marketplace\Listeners;

use App\Modules\Inventory\Events\StockUpdated;
use App\Modules\Inventory\Services\StockService;
use App\Modules\Marketplace\Jobs\SyncMarketplaceListingJob;
use App\Modules\Marketplace\Jobs\NotifyManualCloseJob;
use App\Modules\Marketplace\Models\MarketplaceListing;
use Illuminate\Contracts\Queue\ShouldQueue;

class DispatchMarketplaceSync implements ShouldQueue
{
    public string $queue = 'marketplace-sync';

    public function __construct(private readonly StockService $stockService) {}

    public function handle(StockUpdated $event): void
    {
        $stock = $event->stock;

        // Total available across ALL warehouses for this SKU
        $totalAvailable = $this->stockService->totalAvailableForTenant(
            $stock->tenant_id,
            $stock->product_id,
            $stock->variant_id,
        );

        $listings = MarketplaceListing::where('tenant_id', $stock->tenant_id)
            ->where('product_id', $stock->product_id)
            ->where('variant_id', $stock->variant_id)
            ->whereIn('sync_status', ['active', 'error'])
            ->whereNull('deleted_at')
            ->get();

        foreach ($listings as $listing) {
            $shouldClose = $totalAvailable <= $listing->close_threshold;

            if ($shouldClose && $listing->is_auto_close_enabled) {
                SyncMarketplaceListingJob::dispatch($listing->id, 'close', $totalAvailable)
                    ->onQueue('marketplace-sync')
                    ->delay(now()->addSeconds(3));

            } elseif ($shouldClose && ! $listing->is_auto_close_enabled) {
                NotifyManualCloseJob::dispatch($listing->id, $totalAvailable)
                    ->onQueue('notifications');

            } else {
                SyncMarketplaceListingJob::dispatch($listing->id, 'update_stock', $totalAvailable)
                    ->onQueue('marketplace-sync');
            }
        }
    }
}
