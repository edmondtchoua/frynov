<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Modules\Accounting\Services\BankReconciliationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * RC-43 (P4.3) — rapprochement bancaire : état de pointage d'un compte de banque + pointage/dépointage.
 * Lecture sous accounting.view ; pointage sous accounting.entries.create.
 */
class BankReconciliationController extends Controller
{
    public function __construct(private readonly BankReconciliationService $service) {}

    /** GET /api/accounting/reports/bank-reconciliation?account_id=&statement_balance= */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'account_id'        => ['required', 'uuid'],
            'statement_balance' => ['nullable', 'integer'],
        ]);

        $statement = $request->has('statement_balance') && $request->query('statement_balance') !== ''
            ? (int) $request->query('statement_balance')
            : null;

        return response()->json(['data' => $this->service->state(
            $request->user()->tenant_id, $request->query('account_id'), $statement,
        )]);
    }

    /** POST /api/accounting/reports/bank-reconciliation/point — pointe/dépointe des lignes. */
    public function point(Request $request): JsonResponse
    {
        $data = $request->validate([
            'account_id' => ['required', 'uuid'],
            'line_ids'   => ['required', 'array', 'min:1'],
            'line_ids.*' => ['uuid'],
            'pointed'    => ['nullable', 'boolean'],
        ]);

        $pointed = $request->boolean('pointed', true);
        $this->service->setPointed($request->user()->tenant_id, $data['account_id'], $data['line_ids'], $pointed, $request->user()->id);

        return response()->json(['data' => $this->service->state($request->user()->tenant_id, $data['account_id'])], 201);
    }
}
