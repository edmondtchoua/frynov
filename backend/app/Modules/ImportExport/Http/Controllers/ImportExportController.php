<?php

namespace App\Modules\ImportExport\Http\Controllers;

use App\Modules\ImportExport\Http\Resources\ImportSessionResource;
use App\Modules\ImportExport\Services\ExcelExporter;
use App\Modules\ImportExport\Services\ImportService;
use App\Modules\ImportExport\Services\PdfExporter;
use App\Modules\ImportExport\Services\TemplateService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ImportExportController extends Controller
{
    public function __construct(
        private readonly ImportService  $importService,
        private readonly TemplateService $templateService,
        private readonly ExcelExporter  $excelExporter,
        private readonly PdfExporter    $pdfExporter,
    ) {}

    // ── Import: Upload ────────────────────────────────────────────────────────

    /**
     * POST /api/import/upload
     * Upload a file and create a draft ImportSession.
     */
    public function upload(Request $request): JsonResponse
    {
        $data = $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
            'type' => 'required|in:products,customers,suppliers',
            'mode' => 'required|in:create_only,update_only,create_update,simulate',
        ]);

        try {
            $session = $this->importService->upload(
                $request->file('file'),
                $data['type'],
                $data['mode'],
                $request->user()->tenant_id,
                $request->user()->id,
            );

            // Kick off analysis immediately
            $session = $this->importService->analyze($session);

            return response()->json(new ImportSessionResource($session), 201);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    // ── Import: Status ────────────────────────────────────────────────────────

    /**
     * GET /api/import/{id}
     * Fetch session status + preview rows.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        try {
            $session = $this->importService->findOrFail($id, $request->user()->tenant_id);
            $session->load('rows');

            return response()->json(new ImportSessionResource($session));
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return response()->json(['message' => 'Session introuvable.'], 404);
        }
    }

    // ── Import: Update column mapping ─────────────────────────────────────────

    /**
     * PATCH /api/import/{id}/mapping
     * User-corrected column mapping — triggers re-analysis.
     */
    public function updateMapping(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'mapping' => 'required|array',
        ]);

        try {
            $session = $this->importService->findOrFail($id, $request->user()->tenant_id);
            $session = $this->importService->updateMapping($session, $request->input('mapping'));

            return response()->json(new ImportSessionResource($session));
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    // ── Import: Approve ───────────────────────────────────────────────────────

    /**
     * POST /api/import/{id}/approve
     */
    public function approve(Request $request, string $id): JsonResponse
    {
        try {
            $session = $this->importService->findOrFail($id, $request->user()->tenant_id);
            $session = $this->importService->approve($session, $request->user()->id);

            return response()->json(new ImportSessionResource($session));
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    // ── Import: Execute ───────────────────────────────────────────────────────

    /**
     * POST /api/import/{id}/execute
     */
    public function execute(Request $request, string $id): JsonResponse
    {
        try {
            $session = $this->importService->findOrFail($id, $request->user()->tenant_id);
            $session = $this->importService->execute($session);

            return response()->json(new ImportSessionResource($session));
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    // ── Import: Cancel ────────────────────────────────────────────────────────

    /**
     * DELETE /api/import/{id}
     */
    public function cancel(Request $request, string $id): JsonResponse
    {
        try {
            $session = $this->importService->findOrFail($id, $request->user()->tenant_id);
            $this->importService->cancel($session);

            return response()->json(null, 204);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    // ── Import: History ───────────────────────────────────────────────────────

    /**
     * GET /api/import/history
     */
    public function history(Request $request): JsonResponse
    {
        $sessions = $this->importService->history(
            $request->user()->tenant_id,
            $request->only(['type', 'status'])
        );

        return response()->json([
            'data' => ImportSessionResource::collection($sessions),
            'meta' => [
                'current_page' => $sessions->currentPage(),
                'last_page'    => $sessions->lastPage(),
                'total'        => $sessions->total(),
                'per_page'     => $sessions->perPage(),
            ],
        ]);
    }

    // ── Templates ─────────────────────────────────────────────────────────────

    /**
     * GET /api/import/template/{type}
     */
    public function downloadTemplate(Request $request, string $type)
    {
        try {
            return $this->templateService->download($type, $request->user()?->tenant_id);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }
    }

    // ── Export: Excel ─────────────────────────────────────────────────────────

    /**
     * GET /api/export/{type}?format=xlsx|pdf
     */
    public function export(Request $request, string $type)
    {
        $format   = $request->query('format', 'xlsx');
        $tenantId = $request->user()->tenant_id;
        $filters  = $request->only(['status', 'category_id']);

        if ($format === 'pdf') {
            return match ($type) {
                'products'  => $this->pdfExporter->exportProducts($tenantId, $filters),
                'customers' => $this->pdfExporter->exportCustomers($tenantId),
                'suppliers' => $this->pdfExporter->exportSuppliers($tenantId),
                default     => response()->json(['message' => 'Type inconnu.'], 404),
            };
        }

        return match ($type) {
            'products'  => $this->excelExporter->exportProducts($tenantId, $filters),
            'customers' => $this->excelExporter->exportCustomers($tenantId, $filters),
            'suppliers' => $this->excelExporter->exportSuppliers($tenantId, $filters),
            default     => response()->json(['message' => 'Type inconnu.'], 404),
        };
    }

    // ── Export: Import report PDF ─────────────────────────────────────────────

    /**
     * GET /api/import/{id}/report
     */
    public function downloadReport(Request $request, string $id)
    {
        try {
            $session = $this->importService->findOrFail($id, $request->user()->tenant_id);
            return $this->pdfExporter->exportImportReport($session);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return response()->json(['message' => 'Session introuvable.'], 404);
        }
    }
}
