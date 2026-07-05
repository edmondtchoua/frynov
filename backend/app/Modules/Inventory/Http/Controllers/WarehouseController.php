<?php
namespace App\Modules\Inventory\Http\Controllers;

use App\Modules\Inventory\Models\Warehouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class WarehouseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $warehouses = Warehouse::where('tenant_id', $request->user()->tenant_id)
            ->orderBy('is_default', 'desc')->orderBy('sort_order')->get();
        return response()->json(['data' => $warehouses]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'         => 'required|string|max:150',
            'code'         => 'required|string|max:50',
            'type'         => 'in:warehouse,shop,dropship,virtual',
            'address'      => 'nullable|array',
            'phone'        => 'nullable|string|max:30',
            'email'        => 'nullable|email|max:150',
            'currency'     => 'string|size:3',
            'is_active'    => 'boolean',
            'sells_online' => 'boolean',
            'sort_order'   => 'integer',
        ]);
        app(\App\Modules\Billing\Services\QuotaService::class)->assertCanAddWarehouse($request->user()->tenant);
        $warehouse = Warehouse::create([...$data, 'tenant_id' => $request->user()->tenant_id]);
        return response()->json(['data' => $warehouse], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $wh = Warehouse::where('tenant_id', $request->user()->tenant_id)->findOrFail($id);
        $wh->update($request->only(['name', 'address', 'phone', 'email', 'is_active', 'sells_online', 'sort_order']));
        return response()->json(['data' => $wh->fresh()]);
    }

    public function setDefault(Request $request, string $id): JsonResponse
    {
        $tid = $request->user()->tenant_id;
        Warehouse::where('tenant_id', $tid)->update(['is_default' => false]);
        Warehouse::where('tenant_id', $tid)->findOrFail($id)->update(['is_default' => true]);
        return response()->json(['message' => 'Entrepôt par défaut mis à jour.']);
    }
}
