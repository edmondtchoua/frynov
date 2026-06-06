<?php
namespace App\Modules\Marketplace\Adapters;
use App\Modules\Marketplace\Contracts\MarketplaceAdapterInterface;
use Illuminate\Support\Facades\Http;

/** Jumia Seller Center API — West Africa e-commerce. */
class JumiaAdapter implements MarketplaceAdapterInterface
{
    private string $baseUrl; private string $apiKey; private string $sellerId;
    public function __construct(private readonly string $tenantId, array $config) {
        $country        = $config['country'] ?? 'ci'; // ci, sn, gh, ng...
        $this->baseUrl  = "https://sellercenter.jumia.com.{$country}/api";
        $this->apiKey   = $config['api_key'] ?? throw new \InvalidArgumentException('Jumia: api_key required');
        $this->sellerId = $config['seller_id'] ?? '';
    }
    public function getPlatformName(): string { return 'jumia'; }
    public function closeListing(string $externalProductId, ?string $externalVariantId): void {
        Http::withBasicAuth($this->sellerId, $this->apiKey)
            ->put("{$this->baseUrl}/products/{$externalProductId}", ['status' => 'inactive']);
    }
    public function reopenListing(string $externalProductId, ?string $externalVariantId): void {
        Http::withBasicAuth($this->sellerId, $this->apiKey)
            ->put("{$this->baseUrl}/products/{$externalProductId}", ['status' => 'active']);
    }
    public function updateStock(string $externalProductId, ?string $externalVariantId, int $quantity): void {
        $itemId = $externalVariantId ?? $externalProductId;
        Http::withBasicAuth($this->sellerId, $this->apiKey)
            ->put("{$this->baseUrl}/stocks/{$itemId}", ['quantity' => $quantity]);
    }
    public function updatePrice(string $externalProductId, ?string $externalVariantId, int $priceCents, string $currency): void {
        $itemId = $externalVariantId ?? $externalProductId;
        Http::withBasicAuth($this->sellerId, $this->apiKey)
            ->put("{$this->baseUrl}/prices/{$itemId}", ['price' => $priceCents / 100]);
    }
}
