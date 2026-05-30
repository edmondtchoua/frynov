<?php

namespace App\Modules\Catalog\Services;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use Illuminate\Support\Collection;

class LabelService
{
    // Supported print formats
    public const FORMAT_THERMAL = 'thermal';   // 58mm Bluetooth thermal printer
    public const FORMAT_A4      = 'a4sheet';   // A4 sticker sheet (3×8 = 24 labels/page)

    public function __construct(
        private readonly ProductCodeService $codeService,
    ) {}

    /**
     * Generate a printable HTML label page.
     *
     * @param  array<array{product: Product, variant?: ProductVariant, copies?: int}>  $items
     * @param  string  $format  'thermal' | 'a4sheet'
     * @param  array   $options  show_price, show_qr, tenant_name
     */
    public function generate(array $items, string $format = self::FORMAT_THERMAL, array $options = []): string
    {
        $options = array_merge([
            'show_price'  => true,
            'show_qr'     => true,
            'tenant_name' => '',
        ], $options);

        $labels = collect($this->buildLabels($items, $options));

        return view("catalog::labels.{$format}", [
            'labels'  => $labels,
            'options' => $options,
            'format'  => $format,
        ])->render();
    }

    /**
     * Build label data from a single product (convenience wrapper).
     *
     * @param  int  $copies  Number of copies to print (e.g. qty received in a delivery)
     */
    public function generateForProduct(
        Product $product,
        int $copies = 1,
        string $format = self::FORMAT_THERMAL,
        array $options = [],
    ): string {
        return $this->generate(
            [['product' => $product, 'copies' => $copies]],
            $format,
            $options,
        );
    }

    /**
     * Build label data for a product with all its variants.
     * Useful for cataloguing a new delivery that contains multiple variants.
     *
     * @param  array<string, int>  $variantCopies  variant_id => copies
     */
    public function generateForVariants(
        Product $product,
        array $variantCopies,
        string $format = self::FORMAT_THERMAL,
        array $options = [],
    ): string {
        $items = [];

        foreach ($product->variants as $variant) {
            $copies = $variantCopies[$variant->id] ?? 0;

            if ($copies > 0) {
                $items[] = ['product' => $product, 'variant' => $variant, 'copies' => $copies];
            }
        }

        return $this->generate($items, $format, $options);
    }

    /** @return array<int, array> */
    private function buildLabels(array $items, array $options): array
    {
        $labels = [];

        foreach ($items as $item) {
            $product = $item['product'];
            $variant = $item['variant'] ?? null;
            $copies  = max(1, (int) ($item['copies'] ?? 1));

            /** @var Product|ProductVariant $model */
            $model = $variant ?? $product;

            $qrSize   = ($options['show_qr'] ?? true) ? 80 : 0;
            $qr       = $qrSize > 0 ? $this->codeService->qrCode($model, $qrSize) : null;
            $barcode  = $this->codeService->barcode($model, 'code128');

            $price    = $variant
                ? $variant->effectivePrice()
                : $product->price();

            $labelData = [
                'name'       => $variant?->name ?? $product->name,
                'sku'        => $model->sku,
                'price'      => $price->format(),
                'is_on_sale' => $variant ? false : $product->isOnSale(),
                'qr'         => $qr,
                'barcode'    => $barcode,
                'attributes' => $variant ? $this->formatAttributes($variant->attributes) : null,
                'tenant'     => $options['tenant_name'],
            ];

            for ($i = 0; $i < $copies; $i++) {
                $labels[] = $labelData;
            }
        }

        return $labels;
    }

    private function formatAttributes(?array $attributes): ?string
    {
        if (! $attributes) {
            return null;
        }

        return collect($attributes)
            ->map(fn ($v, $k) => ucfirst($k) . ': ' . $v)
            ->join(' · ');
    }
}
