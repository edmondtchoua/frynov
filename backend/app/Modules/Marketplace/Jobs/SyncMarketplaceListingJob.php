<?php
namespace App\Modules\Marketplace\Jobs;

use App\Modules\Marketplace\Models\MarketplaceListing;
use App\Modules\Marketplace\Services\MarketplaceAdapterFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;

class SyncMarketplaceListingJob implements ShouldQueue
{
    use Dispatchable, Queueable, InteractsWithQueue;

    public int $tries   = 3;
    public int $backoff  = 30;  // seconds between retries

    public function __construct(
        private readonly string $listingId,
        private readonly string $action,      // close | update_stock | reopen
        private readonly int    $newQuantity,
    ) {}

    public function handle(MarketplaceAdapterFactory $factory): void
    {
        $listing = MarketplaceListing::with(['product', 'variant'])->findOrFail($this->listingId);

        $listing->update(['sync_status' => 'syncing']);

        try {
            $adapter = $factory->make($listing->platform, $listing->tenant_id, $listing->platform_config ?? []);

            match ($this->action) {
                'close'        => $adapter->closeListing($listing->external_product_id, $listing->external_variant_id),
                'reopen'       => $adapter->reopenListing($listing->external_product_id, $listing->external_variant_id),
                'update_stock' => $adapter->updateStock($listing->external_product_id, $listing->external_variant_id, $this->newQuantity),
            };

            $listing->update([
                'sync_status'       => $this->action === 'close' ? 'closed' : 'active',
                'last_synced_at'    => now(),
                'sync_retry_count'  => 0,
                'sync_error_count'  => 0,
                'last_sync_error'   => null,
            ]);

        } catch (Throwable $e) {
            $listing->increment('sync_retry_count');
            $listing->increment('sync_error_count');
            $listing->update([
                'sync_status'     => 'error',
                'last_sync_error' => [
                    'message'     => $e->getMessage(),
                    'code'        => $e->getCode(),
                    'retries'     => $listing->sync_retry_count,
                    'timestamp'   => now()->toISOString(),
                ],
            ]);

            if ($this->attempts() >= $this->tries) {
                NotifyManualCloseJob::dispatch($this->listingId, $this->newQuantity)
                    ->onQueue('notifications');
                return; // Do not rethrow — we escalated to human
            }

            throw $e;
        }
    }

    public function failed(Throwable $exception): void
    {
        $listing = MarketplaceListing::find($this->listingId);
        if ($listing) {
            $listing->update(['sync_status' => 'error']);
        }
    }
}
