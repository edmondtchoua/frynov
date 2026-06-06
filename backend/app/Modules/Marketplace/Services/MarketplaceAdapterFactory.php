<?php
namespace App\Modules\Marketplace\Services;
use App\Modules\Marketplace\Adapters\FacebookMarketplaceAdapter;
use App\Modules\Marketplace\Adapters\WhatsAppBusinessAdapter;
use App\Modules\Marketplace\Adapters\ShopifyAdapter;
use App\Modules\Marketplace\Adapters\JumiaAdapter;
use App\Modules\Marketplace\Adapters\WooCommerceAdapter;
use App\Modules\Marketplace\Contracts\MarketplaceAdapterInterface;

class MarketplaceAdapterFactory
{
    public function make(string $platform, string $tenantId, array $config = []): MarketplaceAdapterInterface
    {
        return match ($platform) {
            'facebook'          => new FacebookMarketplaceAdapter($tenantId, $config),
            'whatsapp_catalog'  => new WhatsAppBusinessAdapter($tenantId, $config),
            'shopify'           => new ShopifyAdapter($tenantId, $config),
            'jumia'             => new JumiaAdapter($tenantId, $config),
            'woocommerce'       => new WooCommerceAdapter($tenantId, $config),
            default             => throw new \InvalidArgumentException("Unsupported marketplace platform: {$platform}"),
        };
    }

    /** All platforms currently supported */
    public static function supportedPlatforms(): array
    {
        return ['facebook', 'whatsapp_catalog', 'shopify', 'woocommerce', 'jumia'];
    }
}
