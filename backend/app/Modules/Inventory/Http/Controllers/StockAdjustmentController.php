<?php

namespace App\Modules\Inventory\Http\Controllers;

use App\Modules\Inventory\Models\Stock;
use App\Modules\Inventory\Models\StockAdjustmentRequest;
use App\Modules\Inventory\Services\StockAdjustmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class StockAdjustmentController extends Controller
{
    public function __construct(private readonly StockAdjustmentService $service) {}

    /** GET /api/inventory/adjustments — pending requests (admin review queue) */
    public function pending(Request $request): JsonResponse
    {
        $paginator = $this->service->pending($request->user()->tenant_id);

        return response()->json($paginator);
    }

    /** GET /api/inventory/adjustments/history */
    public function history(Request $request): JsonResponse
    {
        $paginator = $this->service->history(
            $request->user()->tenant_id,
            $request->only(['status']),
        );

        return response()->json($paginator);
    }

    /** POST /api/inventory/adjustments — create an adjustment request */
    public function request(Request $request): JsonResponse
    {
        $request->validate([
            'stock_id'      => 'required|uuid',
            'new_quantity'  => 'required|integer|min:0',
            'reason'        => 'required|string|in:' . implode(',', StockAdjustmentRequest::REASONS),
            'note'          => 'nullable|string|max:500',
        ]);

        $stock = Stock::where('tenant_id', $request->user()->tenant_id)
            ->findOrFail($request->input('stock_id'));

        $req = $this->service->request(
            $stock,
            $request->input('new_quantity'),
            $request->input('reason'),
            $request->input('note'),
            $request->user(),
        );

        $status  = $req->isPending() ? 202 : 200;
        $message = $req->isPending()
            ? 'Demande soumise — en attente d\'approbation admin.'
            : 'Ajustement appliqué immédiatement.';

        return response()->json(['data' => $req, 'message' => $message], $status);
    }

    /** POST /api/inventory/adjustments/{id}/approve — admin approves */
    public function approve(Request $request, string $id): JsonResponse
    {
        if (! $request->user()->hasRole(['admin'])) {
            return response()->json(['message' => 'Action réservée aux administrateurs.'], 403);
        }

        $req = StockAdjustmentRequest::where('tenant_id', $request->user()->tenant_id)
            ->findOrFail($id);

        $this->service->approve($req, $request->user());

        return response()->json(['data' => $req->fresh(), 'message' => 'Ajustement approuvé et exécuté.']);
    }

    /** POST /api/inventory/adjustments/{id}/reject */
    public function reject(Request $request, string $id): JsonResponse
    {
        if (! $request->user()->hasRole(['admin'])) {
            return response()->json(['message' => 'Action réservée aux administrateurs.'], 403);
        }

        $request->validate(['reason' => 'required|string|max:500']);

        $req = StockAdjustmentRequest::where('tenant_id', $request->user()->tenant_id)
            ->findOrFail($id);

        $this->service->reject($req, $request->user(), $request->input('reason'));

        return response()->json(['data' => $req->fresh(), 'message' => 'Demande rejetée.']);
    }
}
