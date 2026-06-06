<?php
namespace App\Modules\Marketplace\Http\Controllers;

use App\Modules\Marketplace\Models\MarketplaceListing;
use App\Modules\Marketplace\Models\MarketplaceSyncAlert;
use App\Modules\Marketplace\Services\MarketplaceAdapterFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class MarketplaceListingController extends Controller
{
    /** GET /api/marketplace/listings */
    public function index(Request $request): JsonResponse
    {
        $listings = MarketplaceListing::where('tenant_id', $request->user()->tenant_id)
            ->with(['product:id,name,sku', 'variant:id,sku,name'])
            ->when($request->query('platform'), fn ($q, $p) => $q->where('platform', $p))
            ->when($request->query('status'),   fn ($q, $s) => $q->where('sync_status', $s))
            ->latest()->paginate(20);

        return response()->json($listings);
    }

    /** POST /api/marketplace/listings */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id'             => 'required|uuid',
            'variant_id'             => 'nullable|uuid',
            'warehouse_id'           => 'nullable|uuid',
            'platform'               => 'required|string|in:' . implode(',', MarketplaceAdapterFactory::supportedPlatforms()),
            'external_product_id'    => 'required|string|max:255',
            'external_variant_id'    => 'nullable|string|max:255',
            'external_sku'           => 'nullable|string|max:150',
            'external_url'           => 'nullable|url|max:1024',
            'is_auto_close_enabled'  => 'boolean',
            'is_auto_reopen_enabled' => 'boolean',
            'close_threshold'        => 'integer|min:0',
            'is_price_sync_enabled'  => 'boolean',
            'platform_config'        => 'nullable|array',
        ]);

        $listing = MarketplaceListing::create([
            ...(array)$data,
            'tenant_id'   => $request->user()->tenant_id,
            'sync_status' => 'active',
        ]);

        return response()->json(['data' => $listing], 201);
    }

    /** PATCH /api/marketplace/listings/{id} */
    public function update(Request $request, string $id): JsonResponse
    {
        $listing = MarketplaceListing::where('tenant_id', $request->user()->tenant_id)->findOrFail($id);
        $listing->update($request->only([
            'is_auto_close_enabled', 'is_auto_reopen_enabled',
            'close_threshold', 'is_price_sync_enabled', 'platform_config',
            'external_url',
        ]));
        return response()->json(['data' => $listing->fresh()]);
    }

    /** DELETE /api/marketplace/listings/{id} */
    public function destroy(Request $request, string $id): JsonResponse
    {
        MarketplaceListing::where('tenant_id', $request->user()->tenant_id)->findOrFail($id)->delete();
        return response()->json(['message' => 'Listing supprimé.']);
    }

    /** GET /api/marketplace/alerts */
    public function alerts(Request $request): JsonResponse
    {
        $alerts = MarketplaceSyncAlert::where('tenant_id', $request->user()->tenant_id)
            ->when(! $request->boolean('include_read'), fn ($q) => $q->where('is_read', false))
            ->with('listing:id,platform,external_product_id')
            ->latest()->paginate(20);

        return response()->json($alerts);
    }

    /** PATCH /api/marketplace/alerts/{id}/read */
    public function markRead(Request $request, string $id): JsonResponse
    {
        MarketplaceSyncAlert::where('tenant_id', $request->user()->tenant_id)
            ->findOrFail($id)
            ->update(['is_read' => true, 'resolved_at' => now()]);

        return response()->json(['message' => 'Alerte marquée comme lue.']);
    }

    /** GET /api/marketplace/platforms */
    public function platforms(): JsonResponse
    {
        return response()->json([
            'data' => array_map(fn ($p) => [
                'code'  => $p,
                'label' => match($p) {
                    'facebook'         => 'Facebook Marketplace',
                    'whatsapp_catalog' => 'WhatsApp Business Catalog',
                    'shopify'          => 'Shopify',
                    'jumia'            => 'Jumia',
                    default            => ucfirst($p),
                },
            ], MarketplaceAdapterFactory::supportedPlatforms()),
        ]);
    }
}
