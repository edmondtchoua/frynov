<?php

namespace App\Modules\Marketplace\Adapters;

use App\Modules\Marketplace\Contracts\MarketplaceAdapterInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * WooCommerce REST API v3 adapter.
 *
 * Uses HTTP Basic Auth (Consumer Key / Consumer Secret).
 * API base: {store_url}/wp-json/wc/v3
 *
 * Required platform_config:
 *   store_url        : Base URL of the WooCommerce store (https://mystore.com)
 *   consumer_key     : WooCommerce REST API Consumer Key (ck_...)
 *   consumer_secret  : WooCommerce REST API Consumer Secret (cs_...)
 *   api_version      : API version (default "v3")
 *
 * Documentation: https://woocommerce.github.io/woocommerce-rest-api-docs/
 */
class WooCommerceAdapter implements MarketplaceAdapterInterface
{
    private const DEFAULT_VERSION = 'v3';

    private string $storeUrl;
    private string $consumerKey;
    private string $consumerSecret;
    private string $apiVersion;

    public function __construct(
        private readonly string $tenantId,
        array $config,
    ) {
        $this->storeUrl        = rtrim($config['store_url']       ?? throw new \InvalidArgumentException('WooCommerce: store_url required'), '/');
        $this->consumerKey     = $config['consumer_key']    ?? throw new \InvalidArgumentException('WooCommerce: consumer_key required');
        $this->consumerSecret  = $config['consumer_secret'] ?? throw new \InvalidArgumentException('WooCommerce: consumer_secret required');
        $this->apiVersion      = $config['api_version']    ?? self::DEFAULT_VERSION;
    }

    public function getPlatformName(): string { return 'woocommerce'; }

    // ── Interface implementation ─────────────────────────────────────────────

    /**
     * Close a WooCommerce product:
     * - For a simple product → status = 'draft' (unlisted)
     * - For a variation → set stock = 0, stock_status = 'outofstock'
     */
    public function closeListing(string $externalProductId, ?string $externalVariantId): void
    {
        $response = $externalVariantId
            ? $this->wooRequest('PUT',
                "/products/{$externalProductId}/variations/{$externalVariantId}",
                ['stock_quantity' => 0, 'manage_stock' => true, 'backorders' => 'no', 'stock_status' => 'outofstock']
              )
            : $this->wooRequest('PUT', "/products/{$externalProductId}",
                ['status' => 'draft', 'stock_quantity' => 0, 'manage_stock' => true, 'stock_status' => 'outofstock']
              );

        if (! $response->successful()) {
            $this->throwApiError('closeListing', $response, $externalProductId);
        }

        Log::info('[WooCommerce] Listing closed', [
            'product_id' => $externalProductId,
            'variant_id' => $externalVariantId,
            'tenant'     => $this->tenantId,
        ]);
    }

    /**
     * Reopen: set status = 'publish' (simple) or stock_status = 'instock' (variation).
     */
    public function reopenListing(string $externalProductId, ?string $externalVariantId): void
    {
        $response = $externalVariantId
            ? $this->wooRequest('PUT',
                "/products/{$externalProductId}/variations/{$externalVariantId}",
                ['manage_stock' => true, 'backorders' => 'no', 'stock_status' => 'instock']
              )
            : $this->wooRequest('PUT', "/products/{$externalProductId}",
                ['status' => 'publish', 'stock_status' => 'instock', 'manage_stock' => true]
              );

        if (! $response->successful()) {
            $this->throwApiError('reopenListing', $response, $externalProductId);
        }

        Log::info('[WooCommerce] Listing reopened', ['product_id' => $externalProductId, 'tenant' => $this->tenantId]);
    }

    /**
     * Update stock quantity. Also sets stock_status automatically.
     */
    public function updateStock(string $externalProductId, ?string $externalVariantId, int $quantity): void
    {
        $payload  = [
            'stock_quantity' => max(0, $quantity),
            'manage_stock'   => true,
            'stock_status'   => $quantity > 0 ? 'instock' : 'outofstock',
            'backorders'     => 'no',
        ];
        $endpoint = $externalVariantId
            ? "/products/{$externalProductId}/variations/{$externalVariantId}"
            : "/products/{$externalProductId}";

        $response = $this->wooRequest('PUT', $endpoint, $payload);

        if (! $response->successful()) {
            $this->throwApiError('updateStock', $response, $externalProductId);
        }

        Log::info('[WooCommerce] Stock updated', [
            'product_id' => $externalProductId,
            'variant_id' => $externalVariantId,
            'quantity'   => $quantity,
            'tenant'     => $this->tenantId,
        ]);
    }

    /**
     * Update price. WooCommerce stores prices as decimal strings ("150.00").
     */
    public function updatePrice(string $externalProductId, ?string $externalVariantId, int $priceCents, string $currency): void
    {
        $price    = number_format($priceCents / 100, 2, '.', '');
        $endpoint = $externalVariantId
            ? "/products/{$externalProductId}/variations/{$externalVariantId}"
            : "/products/{$externalProductId}";

        $response = $this->wooRequest('PUT', $endpoint, ['regular_price' => $price]);

        if (! $response->successful()) {
            $this->throwApiError('updatePrice', $response, $externalProductId);
        }

        Log::info('[WooCommerce] Price updated', ['product_id' => $externalProductId, 'price' => $price, 'currency' => $currency]);
    }

    // ── WooCommerce-specific helpers ─────────────────────────────────────────

    /**
     * Batch update up to 100 products per call.
     *
     * @param array $updates [['id' => '123', 'stock_quantity' => 5], ...]
     */
    public function batchUpdateProducts(array $updates): void
    {
        foreach (array_chunk($updates, 100) as $chunk) {
            $response = $this->wooRequest('POST', '/products/batch', ['update' => $chunk]);
            if (! $response->successful()) {
                Log::error('[WooCommerce] Batch update failed', ['error' => $response->json(), 'tenant' => $this->tenantId]);
            }
        }
    }

    /**
     * Find a WooCommerce product by SKU — returns the first match.
     */
    public function findProductBySku(string $sku): ?array
    {
        $response = $this->wooRequest('GET', '/products', ['sku' => $sku]);
        if (! $response->successful()) return null;
        $products = $response->json();
        return ! empty($products) ? $products[0] : null;
    }

    /**
     * Fetch a WooCommerce product or variation by its ID.
     */
    public function getProduct(string $externalProductId, ?string $externalVariantId = null): ?array
    {
        $endpoint = $externalVariantId
            ? "/products/{$externalProductId}/variations/{$externalVariantId}"
            : "/products/{$externalProductId}";
        $response = $this->wooRequest('GET', $endpoint);
        return $response->successful() ? $response->json() : null;
    }

    // ── Private ──────────────────────────────────────────────────────────────

    private function wooRequest(string $method, string $endpoint, array $data = [])
    {
        $url = "{$this->storeUrl}/wp-json/wc/{$this->apiVersion}{$endpoint}";

        return Http::withBasicAuth($this->consumerKey, $this->consumerSecret)
            ->acceptJson()
            ->timeout(20)
            ->retry(2, 1000)
            ->{strtolower($method)}($url, $data);
    }

    private function throwApiError(string $action, $response, string $productId): never
    {
        $body    = $response->json();
        $message = $body['message'] ?? $body['error'] ?? 'Unknown WooCommerce error';
        $code    = $body['code']    ?? $response->status();

        Log::error("[WooCommerce] {$action} failed", [
            'product_id' => $productId,
            'status'     => $response->status(),
            'code'       => $code,
            'message'    => $message,
            'tenant'     => $this->tenantId,
        ]);

        throw new \RuntimeException("[WooCommerce:{$code}] {$action} failed for {$productId}: {$message}");
    }
}
