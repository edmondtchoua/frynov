<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Modules\Accounting\Models\AccountingPeriod;
use App\Modules\Accounting\Models\FiscalYear;
use App\Modules\Platform\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * RC-23 — exercices & périodes comptables. Le verrouillage d'une période interdit toute nouvelle
 * écriture datée dedans (contrôlé par le service Écritures, P2). Réouverture : permission dédiée.
 */
class PeriodController extends Controller
{
    public function __construct(private readonly AuditService $audit) {}

    /** GET /api/accounting/fiscal-years (avec leurs périodes) */
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => FiscalYear::with('periods')->orderByDesc('starts_on')->get(),
        ]);
    }

    /** POST /api/accounting/periods/{id}/lock */
    public function lock(Request $request, string $id): JsonResponse
    {
        $data   = $request->validate(['reason' => ['nullable', 'string', 'max:255']]);
        $period = AccountingPeriod::findOrFail($id);

        if ($period->status !== AccountingPeriod::STATUS_OPEN) {
            return response()->json(['message' => 'Cette période n\'est pas ouverte.'], 422);
        }

        $period->update([
            'status'      => AccountingPeriod::STATUS_LOCKED,
            'locked_by'   => $request->user()->id,
            'locked_at'   => now(),
            'lock_reason' => $data['reason'] ?? null,
        ]);

        $this->audit->log(
            action: 'accounting.period.locked',
            tenantId: $request->user()->tenant_id,
            userId: $request->user()->id,
            subject: $period,
            oldValues: ['status' => AccountingPeriod::STATUS_OPEN],
            newValues: ['status' => AccountingPeriod::STATUS_LOCKED, 'reason' => $data['reason'] ?? null],
        );

        return response()->json(['data' => $period->fresh()]);
    }

    /** POST /api/accounting/periods/{id}/unlock — réouverture CONTRÔLÉE (motif obligatoire). */
    public function unlock(Request $request, string $id): JsonResponse
    {
        $data   = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        $period = AccountingPeriod::findOrFail($id);

        if ($period->status !== AccountingPeriod::STATUS_LOCKED) {
            return response()->json(['message' => 'Seule une période verrouillée peut être rouverte.'], 422);
        }

        $period->update([
            'status'      => AccountingPeriod::STATUS_OPEN,
            'locked_by'   => null,
            'locked_at'   => null,
            'lock_reason' => null,
        ]);

        $this->audit->log(
            action: 'accounting.period.reopened',
            tenantId: $request->user()->tenant_id,
            userId: $request->user()->id,
            subject: $period,
            oldValues: ['status' => AccountingPeriod::STATUS_LOCKED],
            newValues: ['status' => AccountingPeriod::STATUS_OPEN, 'reason' => $data['reason']],
        );

        return response()->json(['data' => $period->fresh()]);
    }
}
