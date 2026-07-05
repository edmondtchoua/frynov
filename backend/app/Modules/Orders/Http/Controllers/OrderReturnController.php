<?php

namespace App\Modules\Orders\Http\Controllers;

use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderReturn;
use App\Modules\Orders\Services\OrderReturnService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class OrderReturnController extends Controller
{
    public function __construct(private readonly OrderReturnService $svc) {}

    /** GET /api/orders/returns */
    public function index(Request $request): JsonResponse
    {
        $returns = OrderReturn::where('tenant_id', $request->user()->tenant_id)
            ->with(['order:id,number,status', 'lines:id,return_id,product_id,quantity_requested,quantity_approved'])
            ->when($request->query('status'),   fn ($q, $s)  => $q->where('status', $s))
            ->when($request->query('order_id'), fn ($q, $id) => $q->where('order_id', $id))
            ->latest()->paginate(20);

        return response()->json($returns);
    }

    /** POST /api/orders/{orderId}/returns */
    public function store(Request $request, string $orderId): JsonResponse
    {
        $data = $request->validate([
            'reason'                  => 'required|in:defective,wrong_item,changed_mind,damaged,other',
            'resolution'              => 'required|in:refund,exchange,store_credit',
            'customer_note'           => 'nullable|string|max:1000',
            'lines'                   => 'required|array|min:1',
            'lines.*.order_line_id'   => 'required|uuid',
            'lines.*.quantity'        => 'required|integer|min:1',
            'lines.*.condition'       => 'nullable|in:resalable,damaged,destroyed',
            'lines.*.reason'          => 'nullable|string|max:200',
        ]);

        $order  = Order::where('tenant_id', $request->user()->tenant_id)->findOrFail($orderId);
        $return = $this->svc->create(
            $order,
            $data['lines'],
            $data['reason'],
            $request->user()->id,
            $data['customer_note'] ?? null,
            $data['resolution'],
        );

        return response()->json(['data' => $return], 201);
    }

    /** GET /api/orders/returns/{id} */
    public function show(Request $request, string $id): JsonResponse
    {
        $return = OrderReturn::where('tenant_id', $request->user()->tenant_id)
            ->with(['order', 'lines.product:id,name,sku', 'lines.variant:id,sku,label'])
            ->findOrFail($id);

        return response()->json(['data' => $return]);
    }

    /** POST /api/orders/returns/{id}/approve */
    public function approve(Request $request, string $id): JsonResponse
    {
        $data   = $request->validate(['internal_note' => 'nullable|string|max:500']);
        $return = OrderReturn::where('tenant_id', $request->user()->tenant_id)->findOrFail($id);
        $this->svc->approve($return, $request->user()->id, [], $data['internal_note'] ?? null);

        return response()->json(['message' => 'Retour approuvé.', 'data' => $return->fresh('lines')]);
    }

    /** POST /api/orders/returns/{id}/restock */
    public function restock(Request $request, string $id): JsonResponse
    {
        $data   = $request->validate(['warehouse_id' => 'nullable|uuid']);
        $return = OrderReturn::where('tenant_id', $request->user()->tenant_id)->findOrFail($id);
        $this->svc->restock($return, $request->user()->id, $data['warehouse_id'] ?? null);

        return response()->json(['message' => 'Articles remis en stock.', 'data' => $return->fresh()]);
    }

    /** POST /api/orders/returns/{id}/reject */
    public function reject(Request $request, string $id): JsonResponse
    {
        $data   = $request->validate(['reason' => 'required|string|max:500']);
        $return = OrderReturn::where('tenant_id', $request->user()->tenant_id)->findOrFail($id);
        $this->svc->reject($return, $request->user()->id, $data['reason']);

        return response()->json(['message' => 'Retour refusé.', 'data' => $return->fresh()]);
    }
}
