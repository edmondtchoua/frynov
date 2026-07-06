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

    /**
     * POST /api/admin/plans — crée un plan (par défaut BROUILLON). Des prix `global` (mensuel/annuel)
     * sont créés à partir des montants fournis pour que le plan soit immédiatement chiffrable (repli
     * marché → global). Un nouveau plan ne devient sélectionnable qu'au statut `active`.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:64', 'alpha_dash', 'unique:plans,code'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price_monthly_cents' => ['required', 'integer', 'min:0'],
            'price_yearly_cents' => ['nullable', 'integer', 'min:0'],
            'currency' => ['sometimes', 'string', 'max:8'],
            'trial_days' => ['sometimes', 'integer', 'min:0'],
            'features' => ['sometimes', 'array'],
            'status' => ['sometimes', 'in:active,draft,archived'],
            'badge' => ['sometimes', 'nullable', 'string', 'max:24'],
            'tax_rate_bps' => ['sometimes', 'integer', 'min:0', 'max:100000'],
            'setup_fee_minor' => ['sometimes', 'integer', 'min:0'],
            'is_public' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer'],
            'limits' => ['sometimes', 'array'],
        ]);

        $currency = strtoupper($validated['currency'] ?? 'XOF');
        $monthly  = (int) $validated['price_monthly_cents'];
        $yearly   = (int) ($validated['price_yearly_cents'] ?? $monthly * 12);

        $plan = Plan::create([
            'code' => $validated['code'],
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'price_monthly_cents' => $monthly,
            'price_yearly_cents' => $yearly,
            'currency' => $currency,
            'trial_days' => $validated['trial_days'] ?? 14,
            'features' => $validated['features'] ?? [],
            'status' => $validated['status'] ?? Plan::STATUS_DRAFT, // par défaut brouillon
            'badge' => $validated['badge'] ?? null,
            'tax_rate_bps' => $validated['tax_rate_bps'] ?? 0,
            'setup_fee_minor' => $validated['setup_fee_minor'] ?? 0,
            'is_active' => true,
            'is_public' => $validated['is_public'] ?? false,
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        $plan->limits()->create($validated['limits'] ?? []);

        // Prix `global` (repli universel) pour rendre le plan chiffrable tout de suite.
        foreach (['monthly' => $monthly, 'yearly' => $yearly] as $interval => $amount) {
            $plan->prices()->create([
                'market_code' => 'global', 'country_code' => null, 'currency' => $currency,
                'interval' => $interval, 'base_amount_minor' => $amount,
                'included_users' => 1, 'extra_user_amount_minor' => 0,
                'is_public' => true, 'sort_order' => $plan->sort_order,
            ]);
        }

        $this->audit->logCreated($request, $plan);

        return response()->json($plan->fresh()->load(['limits', 'prices']), 201);
    }

    /**
     * DELETE /api/admin/plans/{plan} — ARCHIVE (jamais de suppression dure) : le plan sort des offres
     * publiques et n'est plus sélectionnable, mais les abonnements existants restent intacts (protégés
     * par le `plan_snapshot` des demandes).
     */
    public function archive(Request $request, Plan $plan): JsonResponse
    {
        $old = $plan->only(['status', 'is_public']);
        $plan->update(['status' => Plan::STATUS_ARCHIVED, 'is_public' => false]);
        $this->audit->logUpdated($request, $plan, $old, notes: 'plan.archived');

        return response()->json($plan->fresh());
    }

    /**
     * GET /api/admin/plans/analytics — synthèse d'abonnement par plan (adoption + revenu récurrent
     * approximatif) + volume de demandes de changement par statut.
     */
    public function analytics(): JsonResponse
    {
        $byPlan = \App\Modules\Billing\Models\Subscription::query()
            ->selectRaw('plan_id, count(*) as subscriptions, coalesce(sum(amount_paid_minor),0) as revenue_minor')
            ->whereIn('status', ['active', 'trialing'])
            ->groupBy('plan_id')
            ->get()
            ->keyBy('plan_id');

        $plans = Plan::orderBy('sort_order')->get()->map(fn (Plan $p) => [
            'code' => $p->code,
            'name' => $p->name,
            'status' => $p->status,
            'badge' => $p->badge,
            'active_subscriptions' => (int) ($byPlan[$p->id]->subscriptions ?? 0),
            'revenue_minor' => (int) ($byPlan[$p->id]->revenue_minor ?? 0),
        ]);

        $requests = \App\Modules\Billing\Models\SubscriptionChangeRequest::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return response()->json([
            'plans' => $plans,
            'change_requests_by_status' => $requests,
        ]);
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
            // P5 — cycle de vie éditorial.
            'status' => ['sometimes', 'in:active,draft,archived'],
            'badge' => ['sometimes', 'nullable', 'string', 'max:24'],
            // Option — taxes & frais d'installation.
            'tax_rate_bps' => ['sometimes', 'integer', 'min:0', 'max:100000'],
            'setup_fee_minor' => ['sometimes', 'integer', 'min:0'],

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
