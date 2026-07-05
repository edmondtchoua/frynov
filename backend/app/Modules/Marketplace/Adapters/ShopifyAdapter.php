<?php
namespace App\Modules\Marketplace\Adapters;
use App\Modules\Marketplace\Contracts\MarketplaceAdapterInterface;
use Illuminate\Support\Facades\Http;

/** Shopify Partner API — inventory levels & product listing management. */
class ShopifyAdapter implements MarketplaceAdapterInterface
{
    private string $shopDomain; private string $accessToken; private string $locationId;
    public function __construct(private readonly string $tenantId, array $config) {
        $this->shopDomain   = $config['shop_domain'] ?? throw new \InvalidArgumentException('Shopify: shop_domain required');
        $this->accessToken  = $config['access_token'] ?? throw new \InvalidArgumentException('Shopify: access_token required');
        $this->locationId   = $config['location_id'] ?? '';
    }
    public function getPlatformName(): string { return 'shopify'; }
    public function closeListing(string $externalProductId, ?string $externalVariantId): void {
        // PUT /admin/api/2024-01/products/{id}.json  status: draft
        Http::withHeaders(['X-Shopify-Access-Token' => $this->accessToken])
            ->put("https://{$this->shopDomain}/admin/api/2024-01/products/{$externalProductId}.json", ['product' => ['id' => $externalProductId, 'status' => 'draft']]);
    }
    public function reopenListing(string $externalProductId, ?string $externalVariantId): void {
        Http::withHeaders(['X-Shopify-Access-Token' => $this->accessToken])
            ->put("https://{$this->shopDomain}/admin/api/2024-01/products/{$externalProductId}.json", ['product' => ['id' => $externalProductId, 'status' => 'active']]);
    }
    public function updateStock(string $externalProductId, ?string $externalVariantId, int $quantity): void {
        // Uses Inventory Level API
        Http::withHeaders(['X-Shopify-Access-Token' => $this->accessToken])
            ->post("https://{$this->shopDomain}/admin/api/2024-01/inventory_levels/set.json", [
                'location_id'       => $this->locationId,
                'inventory_item_id' => $externalVariantId ?? $externalProductId,
                'available'         => $quantity,
            ]);
    }
    public function updatePrice(string $externalProductId, ?string $externalVariantId, int $priceCents, string $currency): void {
        Http::withHeaders(['X-Shopify-Access-Token' => $this->accessToken])
            ->put("https://{$this->shopDomain}/admin/api/2024-01/variants/{$externalVariantId}.json", [
                'variant' => ['id' => $externalVariantId, 'price' => number_format($priceCents / 100, 2)],
            ]);
    }
}
