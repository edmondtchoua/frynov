<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Modules\Accounting\Services\LedgerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * RC-38 (P4.1) — états comptables de lecture : balance générale et grand livre.
 * RBAC lecture porté par les routes ; aucune mutation.
 */
class ReportController extends Controller
{
    public function __construct(private readonly LedgerService $ledger) {}

    /** GET /api/accounting/reports/trial-balance?from=&to= */
    public function trialBalance(Request $request): JsonResponse
    {
        $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date']]);
        [$from, $to] = $this->range($request);

        return response()->json(['data' => $this->ledger->trialBalance($request->user()->tenant_id, $from, $to)]);
    }

    /** GET /api/accounting/reports/general-ledger?account_id=&from=&to= */
    public function generalLedger(Request $request): JsonResponse
    {
        $request->validate([
            'account_id' => ['required', 'uuid'],
            'from'       => ['nullable', 'date'],
            'to'         => ['nullable', 'date'],
        ]);
        [$from, $to] = $this->range($request);

        return response()->json(['data' => $this->ledger->generalLedger(
            $request->user()->tenant_id, $request->query('account_id'), $from, $to,
        )]);
    }

    /** @return array{0:?string,1:string} [from (nullable), to (défaut aujourd'hui)] */
    private function range(Request $request): array
    {
        return [$request->query('from') ?: null, $request->query('to') ?: now()->toDateString()];
    }
}
