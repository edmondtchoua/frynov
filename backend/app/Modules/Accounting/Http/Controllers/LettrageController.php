<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Modules\Accounting\Services\LettrageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * RC-39 (P4.2) — lettrage des comptes de tiers : consultation des lignes lettrables, lettrage d'un
 * groupe équilibré, délettrage. Lecture sous accounting.view ; mutation sous accounting.entries.create.
 */
class LettrageController extends Controller
{
    public function __construct(private readonly LettrageService $service) {}

    /** GET /api/accounting/reports/lettrage?account_id=&only_open= */
    public function index(Request $request): JsonResponse
    {
        // NB : `only_open` arrive en chaîne ("true"/"false") — `boolean()` la coerce sans échouer
        // (la règle de validation `boolean` rejetterait "false").
        $request->validate(['account_id' => ['required', 'uuid']]);

        return response()->json(['data' => $this->service->accountLines(
            $request->user()->tenant_id, $request->query('account_id'), $request->boolean('only_open'),
        )]);
    }

    /** POST /api/accounting/reports/lettrage — lettre un groupe équilibré. */
    public function letter(Request $request): JsonResponse
    {
        $data = $request->validate([
            'account_id' => ['required', 'uuid'],
            'line_ids'   => ['required', 'array', 'min:2'],
            'line_ids.*' => ['uuid'],
        ]);

        $code = $this->service->letter($request->user()->tenant_id, $data['account_id'], $data['line_ids'], $request->user()->id);

        return response()->json([
            'message' => "Lettrage {$code} créé.",
            'data'    => ['code' => $code, 'account' => $this->service->accountLines($request->user()->tenant_id, $data['account_id'])],
        ], 201);
    }

    /** POST /api/accounting/reports/lettrage/unletter — délettre un groupe. */
    public function unletter(Request $request): JsonResponse
    {
        $data = $request->validate([
            'account_id' => ['required', 'uuid'],
            'code'       => ['required', 'string', 'max:8'],
        ]);

        $count = $this->service->unletter($request->user()->tenant_id, $data['account_id'], $data['code'], $request->user()->id);

        return response()->json([
            'message' => "Lettrage {$data['code']} annulé ({$count} lignes).",
            'data'    => ['account' => $this->service->accountLines($request->user()->tenant_id, $data['account_id'])],
        ]);
    }
}
