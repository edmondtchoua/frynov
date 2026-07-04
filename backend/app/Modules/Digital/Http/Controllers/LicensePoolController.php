<?php

namespace App\Modules\Digital\Http\Controllers;

use App\Modules\Catalog\Models\Product;
use App\Modules\Digital\Models\LicensePoolKey;
use App\Modules\Digital\Services\DigitalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * RC-6E — import et suivi du pool de clés de licence éditeur d'un produit digital.
 * La taille d'import maximale dépend du PLAN du tenant (config `digital.pool_import_limits`).
 */
class LicensePoolController extends Controller
{
    public function __construct(private readonly DigitalService $digital) {}

    /** POST /api/digital/products/{productId}/license-keys — importe des clés (collage/CSV une par ligne). */
    public function import(Request $request, string $productId): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $product = Product::where('tenant_id', $tenantId)->where('id', $productId)->first();
        if (! $product) {
            return response()->json(['message' => 'Produit introuvable.'], 404);
        }
        if ($product->fulfillment_type !== Product::FULFILLMENT_LICENSE) {
            return response()->json(['message' => 'Ce produit ne se livre pas par clé de licence.'], 422);
        }

        $limit = $this->digital->poolImportLimit($tenantId);

        $data = $request->validate([
            'keys'   => ['required', 'array', 'min:1', "max:{$limit}"],
            'keys.*' => ['required', 'string', 'max:190'],
        ], [
            'keys.max' => "Votre abonnement autorise au plus {$limit} clés par import.",
        ]);

        $result = $this->digital->importPoolKeys($tenantId, $product, $data['keys'], $request->user()->id);

        return response()->json(['data' => $result], 201);
    }

    /** GET /api/digital/products/{productId}/license-keys/summary — disponibilité du pool. */
    public function summary(Request $request, string $productId): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $counts = LicensePoolKey::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->where('product_id', $productId)
            ->selectRaw('status, COUNT(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status');

        return response()->json(['data' => [
            'available'           => (int) ($counts[LicensePoolKey::STATUS_AVAILABLE] ?? 0),
            'assigned'            => (int) ($counts[LicensePoolKey::STATUS_ASSIGNED] ?? 0),
            'exhaustion_behavior' => $this->digital->poolExhaustionBehavior($tenantId),
            'import_limit'        => $this->digital->poolImportLimit($tenantId),
        ]]);
    }
}
