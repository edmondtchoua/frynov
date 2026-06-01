<?php
namespace App\Modules\Platform\Http\Controllers;

use App\Modules\Platform\Models\AuditLog;
use App\Modules\Platform\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class AdminAuditController extends Controller
{
    public function __construct(private readonly AuditService $audit) {}

    /** GET /api/admin/audit-logs
     * Filters: tenant_id, action, user_id, risk_level, from_date, to_date
     */
    public function index(Request $request): JsonResponse
    {
        $logs = AuditLog::query()
            ->when($request->query('tenant_id'), fn ($q, $v) => $q->where('tenant_id', $v))
            ->when($request->query('action'),    fn ($q, $v) => $q->where('action', 'like', "%{$v}%"))
            ->when($request->query('user_id'),   fn ($q, $v) => $q->where('user_id', $v))
            ->when($request->query('risk_level'), fn ($q, $v) => $q->where('risk_level', $v))
            ->when($request->query('from_date'),  fn ($q, $v) => $q->where('occurred_at', '>=', $v))
            ->when($request->query('to_date'),    fn ($q, $v) => $q->where('occurred_at', '<=', $v . ' 23:59:59'))
            ->orderByDesc('occurred_at')
            ->paginate((int) $request->query('per_page', 50));

        return response()->json([
            'data' => $logs->items(),
            'meta' => [
                'total'        => $logs->total(),
                'per_page'     => $logs->perPage(),
                'current_page' => $logs->currentPage(),
                'last_page'    => $logs->lastPage(),
                'from'         => $logs->firstItem(),
                'to'           => $logs->lastItem(),
            ],
        ]);
    }

    /** POST /api/admin/audit-logs/verify-chain
     * Verifies HMAC integrity of the last N audit log entries.
     * Returns: { ok: bool, checked: int, first_broken_id: ?string }
     */
    public function verifyChain(Request $request): JsonResponse
    {
        $limit   = min((int) $request->query('limit', 100), 500);
        $entries = AuditLog::orderBy('occurred_at')
            ->limit($limit)
            ->get(['id', 'integrity_hash', 'action', 'user_id', 'tenant_id',
                   'subject_type', 'subject_id', 'old_values', 'new_values',
                   'ip_address', 'occurred_at']);

        $prev  = 'GENESIS';
        $broken = null;

        foreach ($entries as $entry) {
            $payload = json_encode(array_filter([
                'action'       => $entry->action,
                'ip_address'   => $entry->ip_address,
                'new_values'   => $entry->new_values,
                'old_values'   => $entry->old_values,
                'prev'         => $prev,
                'subject_id'   => $entry->subject_id,
                'subject_type' => $entry->subject_type,
                'tenant_id'    => $entry->tenant_id,
                'timestamp'    => $entry->occurred_at?->toIso8601String(),
                'user_id'      => $entry->user_id,
            ], fn ($v) => $v !== null), JSON_UNESCAPED_UNICODE);

            $expected = hash_hmac('sha256', $payload, config('app.key'));

            if (!hash_equals($expected, (string) $entry->integrity_hash)) {
                $broken = $entry->id;
                break;
            }
            $prev = $entry->integrity_hash;
        }

        return response()->json([
            'ok'             => $broken === null,
            'checked'        => $entries->count(),
            'first_broken_id'=> $broken,
        ]);
    }
}
