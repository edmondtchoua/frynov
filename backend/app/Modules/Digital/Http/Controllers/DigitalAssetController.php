<?php

namespace App\Modules\Digital\Http\Controllers;

use App\Modules\Catalog\Models\Product;
use App\Modules\Digital\Models\DigitalAsset;
use App\Modules\Digital\Services\DigitalAssetService;
use App\Modules\Digital\Services\DigitalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * RC-5I — fichiers privés des produits digitaux + téléchargement par lien signé (hors auth).
 */
class DigitalAssetController extends Controller
{
    public function __construct(
        private readonly DigitalAssetService $assets,
        private readonly DigitalService $digital,
    ) {}

    /** POST /api/digital/products/{productId}/assets — upload d'un fichier privé (manager/admin). */
    public function store(Request $request, string $productId): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $product = Product::where('tenant_id', $tenantId)->where('id', $productId)->first();
        if (! $product) {
            return response()->json(['message' => 'Produit introuvable.'], 404);
        }
        if (! $product->isDigital()) {
            return response()->json(['message' => 'Ce produit n\'est pas digital.'], 422);
        }

        // RC-9 F-7 — liste blanche d'extensions + taille max (config). Les types dangereux en rendu
        // inline (html/svg/js…) sont exclus par absence de la liste ; l'extension client est vérifiée
        // ici, le MIME réel (basé contenu) est figé au stockage.
        $allowed = (array) config('digital.upload.allowed_extensions', []);
        $maxKb   = (int) config('digital.upload.max_size_kb', 51200);
        $request->validate([
            'file' => ['required', 'file', "max:{$maxKb}", function ($attr, $value, $fail) use ($allowed) {
                $ext = strtolower($value->getClientOriginalExtension());
                if ($ext === '' || ! in_array($ext, $allowed, true)) {
                    $fail("Type de fichier non autorisé" . ($ext ? " (.{$ext})." : "."));
                }
            }],
        ]);

        $asset = $this->assets->attach($tenantId, $product, $request->file('file'), $request->user()->id);

        return response()->json(['data' => $asset->toApiArray()], 201);
    }

    /** GET /api/digital/products/{productId}/assets — liste des fichiers d'un produit. */
    public function index(Request $request, string $productId): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $assets = $this->assets->forProduct($tenantId, $productId, activeOnly: false)
            ->map(fn (DigitalAsset $a) => $a->toApiArray());

        return response()->json(['data' => $assets, 'count' => $assets->count()]);
    }

    /**
     * GET /api/digital/download/{token}/{asset} — téléchargement par **lien signé** (middleware `signed`,
     * hors auth). Re-vérifie l'accessibilité de l'entitlement (la révocation prime sur un lien déjà émis).
     */
    public function download(string $token, string $asset): StreamedResponse|JsonResponse
    {
        $entitlement = $this->digital->findByTokenGlobal($token);
        if (! $entitlement) {
            return response()->json(['message' => 'Accès introuvable.'], 404);
        }
        if (! $entitlement->isAccessible()) {
            return response()->json(['message' => 'Accès révoqué ou expiré.'], 403);
        }

        $assetModel = $this->assets->findActiveAsset($entitlement->tenant_id, $entitlement->product_id, $asset);
        if (! $assetModel) {
            return response()->json(['message' => 'Fichier introuvable.'], 404);
        }

        return Storage::disk($assetModel->disk)->download($assetModel->path, $assetModel->name);
    }
}
