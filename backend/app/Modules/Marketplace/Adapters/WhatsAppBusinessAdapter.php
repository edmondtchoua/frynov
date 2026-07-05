<?php

namespace App\Modules\Marketplace\Adapters;

use App\Modules\Marketplace\Contracts\MarketplaceAdapterInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * WhatsApp Business Catalog adapter.
 *
 * Uses the WhatsApp Business Platform API (Cloud API) for catalog management.
 * WhatsApp catalogs are linked to a Facebook Business Manager and share
 * the Facebook Catalog infrastructure, but are managed via the WhatsApp Business API.
 *
 * Required platform_config:
 *   phone_number_id    : WhatsApp Business phone number ID
 *   access_token       : System user access token (permanent)
 *   catalog_id         : Facebook/WhatsApp Catalog ID (shared with FB Commerce)
 *   business_account_id: WhatsApp Business Account ID (WABA)
 *   api_version        : Graph API version (default "v19.0")
 *
 * Documentation:
 *   https://developers.facebook.com/docs/whatsapp/cloud-api/catalogs
 *   https://developers.facebook.com/docs/whatsapp/business-management-api/catalogs
 */
class WhatsAppBusinessAdapter implements MarketplaceAdapterInterface
{
    private const DEFAULT_API_VERSION = 'v19.0';
    private const BASE_URL            = 'https://graph.facebook.com';

    private string $phoneNumberId;
    private string $accessToken;
    private string $catalogId;
    private string $businessAccountId;
    private string $apiVersion;

    public function __construct(
        private readonly string $tenantId,
        array $config,
    ) {
        $this->phoneNumberId      = $config['phone_number_id']     ?? throw new \InvalidArgumentException('WhatsApp: phone_number_id required');
        $this->accessToken         = $config['access_token']         ?? throw new \InvalidArgumentException('WhatsApp: access_token required');
        $this->catalogId           = $config['catalog_id']           ?? throw new \InvalidArgumentException('WhatsApp: catalog_id required');
        $this->businessAccountId   = $config['business_account_id']  ?? '';
        $this->apiVersion          = $config['api_version']          ?? self::DEFAULT_API_VERSION;
    }

    public function getPlatformName(): string { return 'whatsapp_catalog'; }

    /**
     * Mark product as unavailable in WhatsApp Catalog.
     * WhatsApp uses the shared Facebook Catalog; "closing" a listing
     * means updating the item's availability to 'out of stock'.
     */
    public function closeListing(string $externalProductId, ?string $externalVariantId): void
    {
        $itemId = $externalVariantId ?? $externalProductId;

        $response = $this->catalogItemUpdate($itemId, [
            'availability' => 'out of stock',
            'quantity'     => 0,
        ]);

        if (! $response->successful()) {
            $this->throwApiError('closeListing', $response, $itemId);
        }

        // Optionally send notification message to business number
        $this->sendBusinessNotification(
            "⚠️ Produit épuisé dans votre catalogue WhatsApp. ID: {$itemId}"
        );

        Log::info('[WhatsApp] Catalog item closed', ['item_id' => $itemId, 'tenant' => $this->tenantId]);
    }

    /**
     * Mark product as available again in WhatsApp Catalog.
     */
    public function reopenListing(string $externalProductId, ?string $externalVariantId): void
    {
        $itemId = $externalVariantId ?? $externalProductId;

        $response = $this->catalogItemUpdate($itemId, [
            'availability' => 'in stock',
        ]);

        if (! $response->successful()) {
            $this->throwApiError('reopenListing', $response, $itemId);
        }

        Log::info('[WhatsApp] Catalog item reopened', ['item_id' => $itemId, 'tenant' => $this->tenantId]);
    }

    /**
     * Update product stock in WhatsApp Catalog.
     */
    public function updateStock(string $externalProductId, ?string $externalVariantId, int $quantity): void
    {
        $itemId = $externalVariantId ?? $externalProductId;

        $response = $this->catalogItemUpdate($itemId, [
            'quantity'     => max(0, $quantity),
            'availability' => $quantity > 0 ? 'in stock' : 'out of stock',
        ]);

        if (! $response->successful()) {
            $this->throwApiError('updateStock', $response, $itemId);
        }

        Log::info('[WhatsApp] Stock updated', ['item_id' => $itemId, 'qty' => $quantity, 'tenant' => $this->tenantId]);
    }

    /**
     * Update price in WhatsApp Catalog.
     * Price format for WhatsApp: integer cents with currency.
     */
    public function updatePrice(string $externalProductId, ?string $externalVariantId, int $priceCents, string $currency): void
    {
        $itemId = $externalVariantId ?? $externalProductId;

        $response = $this->catalogItemUpdate($itemId, [
            'price'    => $priceCents,            // WhatsApp stores in smallest unit
            'currency' => strtoupper($currency),
        ]);

        if (! $response->successful()) {
            $this->throwApiError('updatePrice', $response, $itemId);
        }
    }

    // ── WhatsApp-specific features ───────────────────────────────────────────

    /**
     * Send a text message to the business WhatsApp number (internal alert).
     * Used to notify the business owner when manual action is needed.
     */
    public function sendBusinessNotification(string $message, ?string $recipientPhone = null): void
    {
        if (! $recipientPhone && ! $this->businessAccountId) {
            return; // No recipient configured — skip silently
        }

        try {
            Http::withToken($this->accessToken)
                ->timeout(10)
                ->post(
                    self::BASE_URL . "/{$this->apiVersion}/{$this->phoneNumberId}/messages",
                    [
                        'messaging_product' => 'whatsapp',
                        'to'                => $recipientPhone ?? $this->businessAccountId,
                        'type'              => 'text',
                        'text'              => ['body' => $message],
                    ]
                );
        } catch (\Throwable $e) {
            Log::warning('[WhatsApp] Failed to send business notification', [
                'error'  => $e->getMessage(),
                'tenant' => $this->tenantId,
            ]);
        }
    }

    /**
     * Batch update multiple catalog items.
     * WhatsApp Catalog uses the same Catalog Batch API as Facebook.
     */
    public function batchUpdateStock(array $items): void
    {
        foreach (array_chunk($items, 100) as $chunk) {
            $requests = array_map(fn ($item) => [
                'method' => 'UPDATE',
                'data'   => [
                    'retailer_id' => $item['external_id'],
                    'quantity'    => max(0, $item['quantity']),
                    'availability'=> $item['quantity'] > 0 ? 'in stock' : 'out of stock',
                ],
            ], $chunk);

            Http::withToken($this->accessToken)
                ->post(self::BASE_URL . "/{$this->apiVersion}/{$this->catalogId}/items_batch", [
                    'allow_upsert' => false,
                    'requests'     => $requests,
                ]);
        }
    }

    // ── Private helpers ──────────────────────────────────────────────────────

    private function catalogItemUpdate(string $itemId, array $data)
    {
        return Http::withToken($this->accessToken)
            ->timeout(15)
            ->retry(2, 500)
            ->post(self::BASE_URL . "/{$this->apiVersion}/{$itemId}", $data);
    }

    private function throwApiError(string $action, $response, string $itemId): never
    {
        $errorBody = $response->json();
        $message   = $errorBody['error']['message'] ?? 'Unknown WhatsApp API error';
        $code      = $errorBody['error']['code']    ?? $response->status();

        Log::error("[WhatsApp] {$action} failed", [
            'item_id' => $itemId,
            'code'    => $code,
            'message' => $message,
            'tenant'  => $this->tenantId,
        ]);

        throw new \RuntimeException("[WhatsApp:{$code}] {$action} failed for {$itemId}: {$message}");
    }
}
