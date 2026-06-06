<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Services\CatalogService;
use App\Modules\Catalog\Services\LabelService;
use App\Modules\Tenants\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

class LabelController extends Controller
{
    public function __construct(
        private readonly LabelService $labelService,
        private readonly CatalogService $catalog,
    ) {}

    /**
     * GET /api/catalog/products/{id}/label
     * Print labels for a single product.
     *
     * Query params:
     *   copies  (int, default 1)   — for a delivery of N units
     *   format  (thermal|a4sheet)  — printer type
     *   price   (1|0)              — show price on label
     *   qr      (1|0)              — show QR code
     */
    public function product(Request $request, string $productId): Response
    {
        $tenantId = $this->resolveTenantId($request);
        $product  = $this->catalog->findProduct($tenantId, $productId);

        abort_unless($product, 404, 'Produit introuvable.');

        $html = $this->labelService->generateForProduct(
            $product,
            copies : max(1, (int) $request->query('copies', 1)),
            format : $this->resolveFormat($request),
            options: $this->resolveOptions($request, $tenantId),
        );

        return response($html, 200)->header('Content-Type', 'text/html; charset=UTF-8');
    }

    /**
     * GET /api/catalog/products/{id}/variants/{variantId}/label
     * Print labels for a specific variant.
     */
    public function variant(Request $request, string $productId, string $variantId): Response
    {
        $tenantId = $this->resolveTenantId($request);
        $product  = $this->catalog->findProduct($tenantId, $productId);

        abort_unless($product, 404, 'Produit introuvable.');

        $variant = ProductVariant::where('product_id', $product->id)
            ->where('tenant_id', $tenantId)
            ->with('product')
            ->find($variantId);

        abort_unless($variant, 404, 'Variante introuvable.');

        $html = $this->labelService->generate(
            [['product' => $product, 'variant' => $variant, 'copies' => max(1, (int) $request->query('copies', 1))]],
            $this->resolveFormat($request),
            $this->resolveOptions($request, $tenantId),
        );

        return response($html, 200)->header('Content-Type', 'text/html; charset=UTF-8');
    }

    /**
     * POST /api/catalog/products/labels/batch
     * Print labels for a full delivery (multiple products × quantities).
     *
     * Body:
     * {
     *   "format": "thermal",
     *   "items": [
     *     {"product_id": "uuid", "copies": 50},
     *     {"product_id": "uuid", "variant_id": "uuid", "copies": 20}
     *   ],
     *   "show_price": true,
     *   "show_qr": true
     * }
     *
     * This is the key endpoint for receiving a delivery:
     * you know qty per product → print that many labels → stick on boxes.
     */
    public function batch(Request $request): Response
    {
        $data = $request->validate([
            'items'                => ['required', 'array', 'min:1', 'max:200'],
            'items.*.product_id'   => ['required', 'uuid'],
            'items.*.variant_id'   => ['nullable', 'uuid'],
            'items.*.copies'       => ['required', 'integer', 'min:1', 'max:500'],
            'format'               => ['nullable', 'in:thermal,a4sheet'],
            'show_price'           => ['nullable', 'boolean'],
            'show_qr'              => ['nullable', 'boolean'],
        ]);

        $tenantId = $this->resolveTenantId($request);
        $options  = $this->resolveOptions($request, $tenantId);
        $format   = $data['format'] ?? LabelService::FORMAT_THERMAL;

        $items = [];
        foreach ($data['items'] as $row) {
            $product = $this->catalog->findProduct($tenantId, $row['product_id']);

            if (! $product) {
                continue;
            }

            $item = ['product' => $product, 'copies' => $row['copies']];

            if (! empty($row['variant_id'])) {
                $variant = ProductVariant::where('product_id', $product->id)
                    ->where('tenant_id', $tenantId)
                    ->with('product')
                    ->find($row['variant_id']);

                if ($variant) {
                    $item['variant'] = $variant;
                }
            }

            $items[] = $item;
        }

        abort_if(empty($items), 422, 'Aucun produit valide trouvé.');

        $totalLabels = collect($items)->sum(fn ($i) => $i['copies']);
        abort_if($totalLabels > 5000, 422, 'Maximum 5 000 étiquettes par génération.');

        $html = $this->labelService->generate($items, $format, $options);

        return response($html, 200)->header('Content-Type', 'text/html; charset=UTF-8');
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    private function resolveTenantId(Request $request): string
    {
        return $request->user()->tenant_id;
    }

    private function resolveFormat(Request $request): string
    {
        $format = $request->query('format', LabelService::FORMAT_THERMAL);

        return in_array($format, [LabelService::FORMAT_THERMAL, LabelService::FORMAT_A4], true)
            ? $format
            : LabelService::FORMAT_THERMAL;
    }

    private function resolveOptions(Request $request, string $tenantId): array
    {
        $tenant = Tenant::find($tenantId);

        return [
            'show_price'  => filter_var($request->query('price', true), FILTER_VALIDATE_BOOLEAN),
            'show_qr'     => filter_var($request->query('qr', true), FILTER_VALIDATE_BOOLEAN),
            'tenant_name' => $tenant?->name ?? '',
        ];
    }
}
