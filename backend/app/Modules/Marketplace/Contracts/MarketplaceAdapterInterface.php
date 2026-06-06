<?php
namespace App\Modules\Marketplace\Contracts;

interface MarketplaceAdapterInterface
{
    /** Close / deactivate a listing when stock = 0 */
    public function closeListing(string $externalProductId, ?string $externalVariantId): void;

    /** Reopen a listing when stock is replenished */
    public function reopenListing(string $externalProductId, ?string $externalVariantId): void;

    /** Update available quantity on the platform */
    public function updateStock(string $externalProductId, ?string $externalVariantId, int $quantity): void;

    /** Update price on the platform (optional, guarded by is_price_sync_enabled) */
    public function updatePrice(string $externalProductId, ?string $externalVariantId, int $priceCents, string $currency): void;

    /** Returns platform name slug (facebook, whatsapp_catalog, shopify...) */
    public function getPlatformName(): string;
}
