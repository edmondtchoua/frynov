<?php
namespace App\Modules\Inventory\Http\Controllers;

use App\Modules\Inventory\Models\FiscalPeriod;
use App\Modules\Inventory\Services\PeriodLockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class FiscalPeriodController extends Controller
{
    public function __construct(private readonly PeriodLockService $svc) {}

    public function index(Request $request): JsonResponse
    {
        $periods = FiscalPeriod::where('tenant_id', $request->user()->tenant_id)->latest('starts_at')->get();
        return response()->json(['data' => $periods]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'       => 'required|string|max:150',
            'type'       => 'required|in:annual,quarterly,monthly',
            'starts_at'  => 'required|date',
            'ends_at'    => 'required|date|after:starts_at',
        ]);
        $period = FiscalPeriod::create([...(array)$data, 'tenant_id' => $request->user()->tenant_id]);
        return response()->json(['data' => $period], 201);
    }

    public function lock(Request $request, string $id): JsonResponse
    {
        if (! $request->user()->hasRole('admin')) {
            return response()->json(['message' => 'Réservé aux administrateurs.'], 403);
        }
        $request->validate(['reason' => 'required|string|max:500']);
        $period = FiscalPeriod::where('tenant_id', $request->user()->tenant_id)->findOrFail($id);
        $this->svc->lock($period, $request->user()->id, $request->input('reason'));
        return response()->json(['message' => 'Période verrouillée définitivement.', 'data' => $period->fresh()]);
    }

    public function verify(Request $request, string $id): JsonResponse
    {
        $period = FiscalPeriod::where('tenant_id', $request->user()->tenant_id)->findOrFail($id);
        $ok = $this->svc->verifyIntegrity($period);
        return response()->json(['data' => ['period_id' => $id, 'integrity_ok' => $ok, 'status' => $period->status]]);
    }
}
