<?php
namespace App\Modules\Inventory\Http\Controllers;

use App\Modules\Inventory\Models\StockTransfer;
use App\Modules\Inventory\Services\StockTransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class StockTransferController extends Controller
{
    public function __construct(private readonly StockTransferService $svc) {}

    /** GET /api/inventory/transfers */
    public function index(Request $request): JsonResponse
    {
        $transfers = StockTransfer::where('tenant_id', $request->user()->tenant_id)
            ->with(['sourceWarehouse:id,name,code', 'destinationWarehouse:id,name,code'])
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->latest()->paginate(20);
        return response()->json($transfers);
    }

    /** POST /api/inventory/transfers */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'source_warehouse_id'      => 'required|uuid',
            'destination_warehouse_id' => 'required|uuid|different:source_warehouse_id',
            'notes'                    => 'nullable|string|max:500',
            'lines'                    => 'required|array|min:1',
            'lines.*.product_id'       => 'required|uuid',
            'lines.*.variant_id'       => 'nullable|uuid',
            'lines.*.quantity'         => 'required|integer|min:1',
        ]);

        $transfer = $this->svc->create(
            $request->user()->tenant_id,
            $data['source_warehouse_id'],
            $data['destination_warehouse_id'],
            $data['lines'],
            $request->user()->id,
            $data['notes'] ?? null,
        );

        return response()->json(['data' => $transfer->load('lines')], 201);
    }

    /** GET /api/inventory/transfers/{id} */
    public function show(Request $request, string $id): JsonResponse
    {
        $t = StockTransfer::where('tenant_id', $request->user()->tenant_id)
            ->with(['lines.product:id,name,sku', 'sourceWarehouse:id,name,code', 'destinationWarehouse:id,name,code'])
            ->findOrFail($id);
        return response()->json(['data' => $t]);
    }

    /** POST /api/inventory/transfers/{id}/ship */
    public function ship(Request $request, string $id): JsonResponse
    {
        $transfer = StockTransfer::where('tenant_id', $request->user()->tenant_id)->findOrFail($id);
        $quantities = $request->input('quantities', []);
        $this->svc->ship($transfer, $request->user()->id, $quantities);
        return response()->json(['message' => 'Transfert expédié.', 'data' => $transfer->fresh('lines')]);
    }

    /** POST /api/inventory/transfers/{id}/receive */
    public function receive(Request $request, string $id): JsonResponse
    {
        $request->validate(['quantities' => 'required|array']);
        $transfer = StockTransfer::where('tenant_id', $request->user()->tenant_id)->findOrFail($id);
        $this->svc->receive($transfer, $request->user()->id, $request->input('quantities'));
        return response()->json(['message' => 'Réception enregistrée.', 'data' => $transfer->fresh('lines')]);
    }

    /** POST /api/inventory/transfers/{id}/resolve */
    public function resolve(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'resolution' => 'required|in:accept_partial,restock_source,write_off',
            'reason'     => 'required|string|max:500',
        ]);
        $transfer = StockTransfer::where('tenant_id', $request->user()->tenant_id)->findOrFail($id);
        $this->svc->resolveDispute($transfer, $request->user()->id, $data['resolution'], $data['reason']);
        return response()->json(['message' => 'Litige résolu.', 'data' => $transfer->fresh()]);
    }
}
