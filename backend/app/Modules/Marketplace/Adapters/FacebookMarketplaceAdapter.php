<?php

namespace App\Modules\Marketplace\Adapters;

use App\Modules\Marketplace\Contracts\MarketplaceAdapterInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Facebook Marketplace / Facebook Commerce adapter.
 *
 * Uses the Facebook Commerce Platform API (Catalog Manager + Inventory API).
 *
 * Required platform_config:
 *   access_token  : long-lived page access token (never expires)
 *   catalog_id    : Facebook Catalog ID
 *   page_id       : Facebook Page ID (for Commerce)
 *   api_version   : Graph API version (default "v19.0")
 *
 * Documentation: https://developers.facebook.com/docs/commerce-platform
 */
class FacebookMarketplaceAdapter implements MarketplaceAdapterInterface
{
    private const DEFAULT_API_VERSION = 'v19.0';
    private const BASE_URL            = 'https://graph.facebook.com';

    private string $accessToken;
    private string $catalogId;
    private string $pageId;
    private string $apiVersion;

    public function __construct(
        private readonly string $tenantId,
        array $config,
    ) {
        $this->accessToken = $config['access_token'] ?? throw new \InvalidArgumentException('Facebook: access_token required');
        $this->catalogId   = $config['catalog_id']   ?? throw new \InvalidArgumentException('Facebook: catalog_id required');
        $this->pageId      = $config['page_id']       ?? '';
        $this->apiVersion  = $config['api_version']   ?? self::DEFAULT_API_VERSION;
    }

    // ── Interface implementation ─────────────────────────────────────────────

    public function getPlatformName(): string { return 'facebook'; }

    /**
     * Close a listing: set availability to OUT_OF_STOCK in Facebook Catalog.
     * FB does not "delete" a listing — we mark it as unavailable.
     */
    public function closeListing(string $externalProductId, ?string $externalVariantId): void
    {
        $itemId = $externalVariantId ?? $externalProductId;

        $response = $this->graphRequest("POST", "/{$itemId}", [
            'availability' => 'out of stock',
        ]);

        if (! $response->successful()) {
            $this->throwApiError('closeListing', $response, $itemId);
        }

        Log::info('[Facebook] Listing closed', ['item_id' => $itemId, 'tenant' => $this->tenantId]);
    }

    /**
     * Reopen: set availability back to IN_STOCK.
     */
    public function reopenListing(string $externalProductId, ?string $externalVariantId): void
    {
        $itemId = $externalVariantId ?? $externalProductId;

        $response = $this->graphRequest("POST", "/{$itemId}", [
            'availability' => 'in stock',
        ]);

        if (! $response->successful()) {
            $this->throwApiError('reopenListing', $response, $itemId);
        }

        Log::info('[Facebook] Listing reopened', ['item_id' => $itemId, 'tenant' => $this->tenantId]);
    }

    /**
     * Update stock quantity via Facebook Catalog Item Update API.
     * Also manages availability status based on quantity.
     */
    public function updateStock(string $externalProductId, ?string $externalVariantId, int $quantity): void
    {
        $itemId = $externalVariantId ?? $externalProductId;

        $payload = [
            'quantity'     => max(0, $quantity),
            'availability' => $quantity > 0 ? 'in stock' : 'out of stock',
        ];

        $response = $this->graphRequest("POST", "/{$itemId}", $payload);

        if (! $response->successful()) {
            $this->throwApiError('updateStock', $response, $itemId);
        }

        Log::info('[Facebook] Stock updated', ['item_id' => $itemId, 'qty' => $quantity, 'tenant' => $this->tenantId]);
    }

    /**
     * Update price on Facebook Catalog Item.
     * Price must be sent as string with currency: "15000 XOF"
     */
    public function updatePrice(string $externalProductId, ?string $externalVariantId, int $priceCents, string $currency): void
    {
        $itemId      = $externalVariantId ?? $externalProductId;
        // Facebook expects whole currency units for most African currencies
        $priceAmount = $priceCents / 100;
        $priceString = number_format($priceAmount, 0, '.', '') . ' ' . strtoupper($currency);

        $response = $this->graphRequest("POST", "/{$itemId}", [
            'price' => $priceString,
        ]);

        if (! $response->successful()) {
            $this->throwApiError('updatePrice', $response, $itemId);
        }
    }

    // ── Facebook-specific helpers ────────────────────────────────────────────

    /**
     * Batch update multiple items at once (more efficient than individual calls).
     * Uses Facebook Catalog Batch API — max 100 items per call.
     *
     * @param array $items [['retailer_id' => '...', 'availability' => '...', 'quantity' => N], ...]
     */
    public function batchUpdate(array $items): void
    {
        foreach (array_chunk($items, 100) as $chunk) {
            $response = Http::withToken($this->accessToken)
                ->post(self::BASE_URL . "/{$this->apiVersion}/{$this->catalogId}/items_batch", [
                    'allow_upsert' => false,
                    'requests'     => array_map(fn ($item) => [
                        'method' => 'UPDATE',
                        'data'   => $item,
                    ], $chunk),
                ]);

            if (! $response->successful()) {
                Log::error('[Facebook] Batch update failed', [
                    'error'  => $response->json(),
                    'tenant' => $this->tenantId,
                ]);
            }
        }
    }

    // ── Private helpers ──────────────────────────────────────────────────────

    private function graphRequest(string $method, string $endpoint, array $data = [])
    {
        return Http::withToken($this->accessToken)
            ->timeout(15)
            ->retry(2, 500)
            ->{strtolower($method)}(
                self::BASE_URL . "/{$this->apiVersion}" . $endpoint,
                $data
            );
    }

    private function throwApiError(string $action, $response, string $itemId): never
    {
        $errorBody = $response->json();
        $message   = $errorBody['error']['message'] ?? 'Unknown Facebook API error';
        $code      = $errorBody['error']['code']    ?? $response->status();

        Log::error("[Facebook] {$action} failed", [
            'item_id' => $itemId,
            'code'    => $code,
            'message' => $message,
            'tenant'  => $this->tenantId,
        ]);

        throw new \RuntimeException("[Facebook:{$code}] {$action} failed for {$itemId}: {$message}");
    }
}
