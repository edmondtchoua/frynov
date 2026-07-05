<?php

namespace App\Modules\Catalog\Services;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use Picqer\Barcode\BarcodeGeneratorSVG;
use Picqer\Barcode\BarcodeGenerator;
use SimpleSoftwareIO\QrCode\Generator as QrCodeGenerator;

class ProductCodeService
{
    public function __construct(
        private readonly QrCodeGenerator $qrGenerator,
        private readonly BarcodeGeneratorSVG $barcodeGenerator,
    ) {}

    /**
     * Generate a QR code SVG for a product or variant.
     * Encodes a JSON payload with SKU, ID and name for POS scanner use.
     */
    public function qrCode(Product|ProductVariant $item, int $size = 200): string
    {
        $payload = json_encode([
            'sku'  => $item->sku,
            'id'   => $item->id,
            'name' => $item instanceof ProductVariant
                ? ($item->name ?? $item->product->name)
                : $item->name,
        ]);

        $svg = (string) $this->qrGenerator
            ->format('svg')
            ->size($size)
            ->margin(1)
            ->generate($payload);

        // Strip XML declaration so the SVG embeds cleanly in HTML and API JSON
        return ltrim(preg_replace('/^<\?xml[^>]+\?>\s*/u', '', $svg));
    }

    /**
     * Generate a barcode SVG for a product or variant.
     *
     * @param string $type 'code128' (default, any SKU) | 'ean13' (requires 12-digit barcode field)
     */
    public function barcode(Product|ProductVariant $item, string $type = 'code128'): string
    {
        [$content, $barcodeType] = match ($type) {
            'ean13'  => [$this->resolveEan($item), BarcodeGenerator::TYPE_EAN_13],
            default  => [$item->sku,               BarcodeGenerator::TYPE_CODE_128],
        };

        return $this->barcodeGenerator->getBarcode($content, $barcodeType, 2, 60);
    }

    /**
     * Return a combined code sheet (QR + barcode) as a structured array.
     *
     * @return array{sku: string, qr: array, barcode: array}
     */
    public function sheet(Product|ProductVariant $item): array
    {
        return [
            'sku'     => $item->sku,
            'qr'      => [
                'format' => 'svg',
                'data'   => $this->qrCode($item),
            ],
            'barcode' => [
                'format' => 'svg',
                'type'   => 'code128',
                'data'   => $this->barcode($item),
            ],
        ];
    }

    private function resolveEan(Product|ProductVariant $item): string
    {
        $ean = $item->barcode;

        if (! $ean || strlen((string) $ean) !== 12) {
            // Fall back to Code128 content if no valid EAN-13 base is stored
            return $item->sku;
        }

        return (string) $ean;
    }
}
