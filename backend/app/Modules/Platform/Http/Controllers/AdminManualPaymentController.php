<?php

namespace App\Modules\Platform\Http\Controllers;

use App\Modules\Billing\Models\ManualPayment;
use App\Modules\Billing\Services\ManualPaymentService;
use App\Modules\Platform\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminManualPaymentController extends Controller
{
    public function __construct(
        private readonly ManualPaymentService $payments,
        private readonly AuditService         $audit,
    ) {}

    /** GET /api/admin/manual-payments?status=pending */
    public function index(Request $request): JsonResponse
    {
        $paginator = $this->payments->list(
            status: $request->input('status') ?: null,
            perPage: 25,
        );

        return response()->json([
            'data' => collect($paginator->items())->map(fn ($p) => $p->toAdminArray())->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'total'        => $paginator->total(),
            ],
        ]);
    }

    /** GET /api/admin/manual-payments/:id */
    public function show(ManualPayment $manualPayment): JsonResponse
    {
        $manualPayment->load(['tenant', 'plan', 'reviewer']);

        return response()->json($manualPayment->toAdminArray());
    }

    /**
     * GET /api/admin/manual-payments/{id}/proof
     * Streams the PRIVATE proof file. Authorized by a short-lived signed URL (so it
     * works in an <img> tag) — see ManualPayment::proofUrl(). Never publicly listable.
     */
    public function proof(string $manualPayment): StreamedResponse
    {
        $payment = ManualPayment::withoutTenantScope()->findOrFail($manualPayment);
        abort_unless($payment->proof_path, 404);

        $disk = Storage::disk('local');
        abort_unless($disk->exists($payment->proof_path), 404);

        return $disk->download($payment->proof_path, $payment->proof_original_filename ?: 'justificatif');
    }

    /** POST /api/admin/manual-payments/:id/approve */
    public function approve(Request $request, string $id): JsonResponse
    {
        // Bypass TenantScope — admin can approve payments from ANY tenant
        $payment = ManualPayment::withoutTenantScope()
            ->with(['tenant', 'plan'])
            ->findOrFail($id);

        if (! $payment->isPending()) {
            return response()->json([
                'message' => 'Ce paiement n\'est pas en attente (statut actuel : ' . $payment->status . ').',
            ], 422);
        }

        $approved = $this->payments->approve($payment, $request->user());
        $this->audit->logFromRequest($request, 'manual_payment.approved', $payment,
            notes: "Tenant: {$approved->tenant?->name}");

        return response()->json([
            'message' => 'Paiement approuvé, abonnement activé.',
            'data'    => $approved->toAdminArray(),
        ]);
    }

    /** POST /api/admin/manual-payments/:id/reject */
    public function reject(Request $request, string $id): JsonResponse
    {
        // Bypass TenantScope — admin can reject payments from ANY tenant
        $payment = ManualPayment::withoutTenantScope()
            ->with(['tenant', 'plan'])
            ->findOrFail($id);

        if (! $payment->isPending()) {
            return response()->json([
                'message' => 'Ce paiement n\'est pas en attente (statut actuel : ' . $payment->status . ').',
            ], 422);
        }

        $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $rejected = $this->payments->reject($payment, $request->user(), $request->input('reason'));
        $this->audit->logFromRequest($request, 'manual_payment.rejected', $payment,
            notes: $request->input('reason'));

        return response()->json([
            'message' => 'Paiement rejeté.',
            'data'    => $rejected->toAdminArray(),
        ]);
    }
}
