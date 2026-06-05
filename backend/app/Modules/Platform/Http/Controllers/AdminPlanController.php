<?php

namespace App\Modules\Platform\Http\Controllers;

use App\Modules\Billing\Models\Plan;
use App\Modules\Platform\Models\AuditLog;
use App\Modules\Platform\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class AdminPlanController extends Controller
{
    public function __construct(private readonly AuditService $audit) {}

    public function index(): JsonResponse
    {
        $plans = Plan::with(['modules', 'prices', 'limits'])->orderBy('sort_order')->get();

        return response()->json($plans);
    }

    public function show(Plan $plan): JsonResponse
    {
        return response()->json($plan->load(['modules', 'prices', 'limits']));
    }

    public function update(Request $request, Plan $plan): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'string'],
            'price_monthly_cents' => ['sometimes', 'integer', 'min:0'],
            'price_yearly_cents' => ['sometimes', 'integer', 'min:0'],
            'max_users' => ['sometimes', 'integer', 'min:0'],
            'max_products' => ['sometimes', 'integer', 'min:0'],
            'max_monthly_orders' => ['sometimes', 'integer', 'min:0'],
            'trial_days' => ['sometimes', 'integer', 'min:0'],
            'features' => ['sometimes', 'array'],
            'is_active' => ['sometimes', 'boolean'],
            'is_public' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer'],

            // Canonical resource quotas (plan_limits row). null = unlimited.
            'limits' => ['sometimes', 'array'],
            'limits.max_products' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'limits.max_monthly_orders' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'limits.max_customers' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'limits.max_branches' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'limits.max_warehouses' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'limits.max_imports_per_month' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'limits.max_api_calls_per_month' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'limits.storage_mb' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ]);

        $planAttributes = array_diff_key($validated, ['limits' => null]);
        $old = $plan->only(array_keys($planAttributes));

        if ($planAttributes !== []) {
            $plan->update($planAttributes);
        }

        // plan_limits is the canonical source QuotaService reads first. Persist any
        // explicit limits, and mirror the overlapping legacy quota fields so an edit made
        // through the legacy form is actually enforced — otherwise the existing plan_limits
        // row would win silently and the admin's change would have no effect.
        $limits = $validated['limits'] ?? [];
        foreach (['max_products', 'max_monthly_orders'] as $field) {
            if (array_key_exists($field, $validated) && ! array_key_exists($field, $limits)) {
                $limits[$field] = $validated[$field];
            }
        }

        if ($limits !== []) {
            $plan->limits()->updateOrCreate([], $limits);
        }

        $this->audit->logUpdated($request, $plan, $old);

        return response()->json($plan->fresh()->load(['limits', 'prices']));
    }

    public function auditLogs(Request $request): JsonResponse
    {
        $paginator = AuditLog::with('user:id,name,email')
            ->latest()
            ->paginate(50);

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }
}
