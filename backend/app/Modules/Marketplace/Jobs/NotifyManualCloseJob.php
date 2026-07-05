<?php
namespace App\Modules\Marketplace\Jobs;

use App\Modules\Marketplace\Models\MarketplaceListing;
use App\Modules\Marketplace\Models\MarketplaceSyncAlert;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class NotifyManualCloseJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function __construct(
        private readonly string $listingId,
        private readonly int    $currentStock,
    ) {}

    public function handle(): void
    {
        $listing = MarketplaceListing::with('product')->findOrFail($this->listingId);
        $platformLabel = strtoupper($listing->platform);

        MarketplaceSyncAlert::create([
            'tenant_id'       => $listing->tenant_id,
            'listing_id'      => $listing->id,
            'severity'        => 'warning',
            'type'            => 'close_failed',
            'requires_action' => true,
            'message'         => sprintf(
                '⚠️ Action requise : le stock de "%s" sur %s est à %d unité(s). ' .
                'La synchronisation automatique a échoué. Veuillez fermer la vente manuellement.',
                $listing->product?->name ?? 'Produit inconnu',
                $platformLabel,
                $this->currentStock,
            ),
            'context' => [
                'product_id'          => $listing->product_id,
                'variant_id'          => $listing->variant_id,
                'platform'            => $listing->platform,
                'external_product_id' => $listing->external_product_id,
                'external_url'        => $listing->external_url,
                'stock'               => $this->currentStock,
                'last_error'          => $listing->last_sync_error,
            ],
        ]);
    }
}
