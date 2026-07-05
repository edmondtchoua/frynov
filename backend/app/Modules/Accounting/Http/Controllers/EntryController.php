<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Modules\Accounting\Models\Entry;
use App\Modules\Accounting\Services\EntryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * RC-25 — écritures comptables : liste, détail, brouillon, comptabilisation, extourne.
 * RBAC porté par les routes (create/post/reverse séparés).
 */
class EntryController extends Controller
{
    public function __construct(private readonly EntryService $service) {}

    /** GET /api/accounting/entries?journal_id=&status=&source_type=&source_id= */
    public function index(Request $request): JsonResponse
    {
        $entries = Entry::query()
            ->with(['lines:id,entry_id,account_id,label,debit_minor,credit_minor', 'journal:id,code,name'])
            ->when($request->query('journal_id'), fn ($q, $j) => $q->where('journal_id', $j))
            ->when($request->query('status'),     fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('source_type'), fn ($q, $t) => $q->where('source_type', $t))
            ->when($request->query('source_id'),  fn ($q, $i) => $q->where('source_id', $i))
            ->latest('entry_date')->latest('created_at')
            ->paginate((int) $request->integer('per_page', 30));

        return response()->json($entries);
    }

    /** GET /api/accounting/entries/{id} */
    public function show(string $id): JsonResponse
    {
        $entry = Entry::with(['lines.account:id,code,name', 'journal:id,code,name'])->findOrFail($id);

        return response()->json(['data' => $entry]);
    }

    /** POST /api/accounting/entries — crée un brouillon équilibré. */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'journal_id'            => ['required', 'uuid'],
            'entry_date'            => ['required', 'date'],
            'label'                 => ['required', 'string', 'max:255'],
            'currency'              => ['nullable', 'string', 'size:3'],
            'lines'                 => ['required', 'array', 'min:2'],
            'lines.*.account_id'    => ['required', 'uuid'],
            'lines.*.label'         => ['nullable', 'string', 'max:255'],
            'lines.*.debit_minor'   => ['required', 'integer', 'min:0'],
            'lines.*.credit_minor'  => ['required', 'integer', 'min:0'],
        ]);

        $entry = $this->service->createDraft($data, $request->user()->tenant_id, $request->user()->id);

        return response()->json(['data' => $entry->load('lines')], 201);
    }

    /** POST /api/accounting/entries/{id}/post */
    public function post(Request $request, string $id): JsonResponse
    {
        $entry = Entry::findOrFail($id);
        $entry = $this->service->post($entry, $request->user()->id);

        return response()->json(['message' => 'Écriture comptabilisée.', 'data' => $entry->load('lines')]);
    }

    /** POST /api/accounting/entries/{id}/reverse */
    public function reverse(Request $request, string $id): JsonResponse
    {
        $data     = $request->validate([
            'date'   => ['nullable', 'date'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);
        $entry    = Entry::findOrFail($id);
        $reversal = $this->service->reverse($entry, $request->user()->id, $data['date'] ?? null, $data['reason'] ?? null);

        return response()->json(['message' => 'Écriture extournée.', 'data' => $reversal->load('lines')], 201);
    }
}
