<?php

namespace App\Modules\Demo\Http\Controllers;

use App\Modules\Demo\Models\DemoRequest;
use App\Modules\Demo\Services\DemoAccessService;
use App\Modules\Demo\Services\DemoProvisioningService;
use App\Modules\Platform\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Back-office super-admin : gestion des demandes de démo (RequireAdmin).
 */
class AdminDemoRequestController extends Controller
{
    public function __construct(
        private readonly DemoAccessService $access,
        private readonly DemoProvisioningService $provisioning,
        private readonly AuditService $audit,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = DemoRequest::query()->latest();

        if ($status = $request->query('status')) {
            $query->status($status);
        }
        if ($term = $request->query('q')) {
            $query->search($term);
        }

        return response()->json([
            'data'  => $query->paginate((int) $request->query('per_page', 20)),
            'stats' => $this->stats(),
        ]);
    }

    public function stats(): array
    {
        $counts = DemoRequest::query()
            ->selectRaw('status, COUNT(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status');

        return [
            'total'       => (int) $counts->sum(),
            'new'         => (int) ($counts[DemoRequest::STATUS_NEW] ?? 0) + (int) ($counts[DemoRequest::STATUS_PENDING_REVIEW] ?? 0),
            'sent'        => (int) ($counts[DemoRequest::STATUS_ACCESS_SENT] ?? 0),
            'expired'     => (int) ($counts[DemoRequest::STATUS_EXPIRED] ?? 0),
            'rejected'    => (int) ($counts[DemoRequest::STATUS_REJECTED] ?? 0),
            'converted'   => (int) ($counts[DemoRequest::STATUS_CONVERTED] ?? 0),
            'active_demo' => DemoRequest::query()
                ->where('status', DemoRequest::STATUS_ACCESS_SENT)
                ->whereNotNull('demo_access_expires_at')
                ->where('demo_access_expires_at', '>', now())
                ->count(),
        ];
    }

    public function show(DemoRequest $demoRequest): JsonResponse
    {
        return response()->json(['data' => $demoRequest]);
    }

    /** Approuve la demande : provisionne le tenant démo et envoie les accès. */
    public function approve(Request $request, DemoRequest $demoRequest): JsonResponse
    {
        $actorId = $request->user()?->id;
        $this->access->grant($demoRequest, $actorId);
        $this->audit->log('demo.access_granted', null, $actorId, $demoRequest, notes: $demoRequest->email);

        return response()->json(['data' => $demoRequest->refresh()]);
    }

    /** Renvoie les accès (nouveau mot de passe temporaire). */
    public function resend(Request $request, DemoRequest $demoRequest): JsonResponse
    {
        $this->access->resend($demoRequest);
        $this->audit->log('demo.access_resent', null, $request->user()?->id, $demoRequest, notes: $demoRequest->email);

        return response()->json(['data' => $demoRequest->refresh()]);
    }

    /** Rejette la demande. */
    public function reject(Request $request, DemoRequest $demoRequest): JsonResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:255']]);

        $demoRequest->update([
            'status'           => DemoRequest::STATUS_REJECTED,
            'rejection_reason' => $data['reason'] ?? null,
            'reviewed_by'      => $request->user()?->id,
            'reviewed_at'      => now(),
        ]);
        $this->audit->log('demo.rejected', null, $request->user()?->id, $demoRequest, notes: $data['reason'] ?? null);

        return response()->json(['data' => $demoRequest->refresh()]);
    }

    /** Expire/révoque manuellement l'accès (démonte le tenant démo). */
    public function expire(Request $request, DemoRequest $demoRequest): JsonResponse
    {
        $this->provisioning->revoke($demoRequest);
        $this->audit->log('demo.expired_manual', null, $request->user()?->id, $demoRequest);

        return response()->json(['data' => $demoRequest->refresh()]);
    }

    /** Marque la demande comme convertie en client. */
    public function convert(Request $request, DemoRequest $demoRequest): JsonResponse
    {
        $demoRequest->update([
            'status'       => DemoRequest::STATUS_CONVERTED,
            'converted_at' => now(),
        ]);
        $this->audit->log('demo.converted', null, $request->user()?->id, $demoRequest, notes: $demoRequest->email);

        return response()->json(['data' => $demoRequest->refresh()]);
    }

    /** Met à jour les notes internes. */
    public function notes(Request $request, DemoRequest $demoRequest): JsonResponse
    {
        $data = $request->validate(['internal_notes' => ['nullable', 'string', 'max:5000']]);
        $demoRequest->update(['internal_notes' => $data['internal_notes'] ?? null]);

        return response()->json(['data' => $demoRequest->refresh()]);
    }
}
