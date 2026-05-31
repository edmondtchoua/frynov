<?php

namespace App\Modules\Platform\Http\Controllers;

use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Services\SubscriptionService;
use App\Modules\Platform\Services\AuditService;
use App\Modules\Tenants\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class AdminTenantController extends Controller
{
    public function __construct(
        private readonly SubscriptionService $subscriptions,
        private readonly AuditService $audit,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Tenant::withTrashed()
            ->with(['users' => fn ($q) => $q->select('id', 'tenant_id', 'name', 'email')])
            ->latest();

        if ($search = $request->input('search')) {
            $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('slug', 'like', "%{$search}%")
                ->orWhere('domain', 'like', "%{$search}%"));
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($plan = $request->input('plan')) {
            $query->where('plan', $plan);
        }

        $paginator = $query->paginate(25);

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
            ],
        ]);
    }

    public function show(Tenant $tenant): JsonResponse
    {
        $tenant->load([
            'users:id,tenant_id,name,email,is_super_admin,created_at',
        ]);

        $subscription = $this->subscriptions->current($tenant)?->load('plan');

        return response()->json([
            'tenant'       => $tenant,
            'subscription' => $subscription,
        ]);
    }

    public function update(Request $request, Tenant $tenant): JsonResponse
    {
        $validated = $request->validate([
            'name'   => ['sometimes', 'string', 'max:255'],
            'status' => ['sometimes', 'in:active,suspended,cancelled'],
            'domain' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        $old = $tenant->only(['name', 'status', 'domain']);
        $tenant->update($validated);

        $this->audit->logUpdated($request, $tenant, $old);

        return response()->json($tenant->fresh());
    }

    public function suspend(Request $request, Tenant $tenant): JsonResponse
    {
        $this->subscriptions->suspend($tenant, $request->input('reason'));
        $this->audit->logFromRequest($request, 'admin.tenant.suspended', $tenant,
            notes: $request->input('reason'));

        return response()->json(['message' => 'Tenant suspendu.']);
    }

    public function reactivate(Request $request, Tenant $tenant): JsonResponse
    {
        $this->subscriptions->reactivate($tenant, $request->user());
        $this->audit->logFromRequest($request, 'admin.tenant.reactivated', $tenant);

        return response()->json(['message' => 'Tenant réactivé.']);
    }

    public function changePlan(Request $request, Tenant $tenant): JsonResponse
    {
        $request->validate([
            'plan_code' => ['required', 'exists:plans,code'],
        ]);

        $plan    = Plan::where('code', $request->input('plan_code'))->firstOrFail();
        $oldPlan = $tenant->plan;

        $subscription = $this->subscriptions->changePlan($tenant, $plan, $request->user());
        $this->audit->logPlanChanged($request, $tenant->id, $oldPlan, $plan->code);

        return response()->json($subscription->load('plan'));
    }
}
