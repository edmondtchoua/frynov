<?php

namespace App\Modules\Platform\Http\Controllers;

use App\Modules\Billing\Services\SubscriptionService;
use App\Modules\Platform\Services\ModuleRegistryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ModulesController extends Controller
{
    public function __construct(
        private readonly ModuleRegistryService $registry,
        private readonly SubscriptionService   $subscriptions,
    ) {}

    /**
     * Returns all visible ERP modules with tenant_active status.
     * Used by the frontend to render the module dashboard and navigation.
     */
    public function forCurrentTenant(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        if (! $tenant) {
            return response()->json(['data' => [], 'active_codes' => []]);
        }

        $modules     = $this->registry->listForTenant($tenant);
        $activeCodes = $this->registry->activeCodes($tenant);

        return response()->json([
            'data'         => $modules,
            'active_codes' => $activeCodes,
        ]);
    }

    /**
     * Returns the current subscription for the authenticated tenant.
     * Used by the frontend Settings > Billing tab.
     */
    public function currentSubscription(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        if (! $tenant) {
            return response()->json(['subscription' => null]);
        }

        $sub = $this->subscriptions->current($tenant)?->load('plan');

        if (! $sub) {
            return response()->json(['subscription' => null]);
        }

        return response()->json([
            'subscription' => [
                'id'                 => $sub->id,
                'plan_code'          => $sub->plan?->code ?? $tenant->plan,
                'plan_name'          => $sub->plan?->name ?? ucfirst((string) $tenant->plan),
                'plan_price_monthly' => $sub->plan?->price_monthly_cents,
                'plan_price_yearly'  => $sub->plan?->price_yearly_cents,
                'currency'           => $sub->plan?->currency ?? 'XOF',
                'max_users'          => $sub->plan?->max_users,
                'max_products'       => $sub->plan?->max_products,
                'max_monthly_orders' => $sub->plan?->max_monthly_orders,
                'features'           => $sub->plan?->features ?? [],
                'status'             => $sub->status,
                'trial_ends_at'      => $sub->trial_ends_at?->toISOString(),
                'current_period_end' => $sub->current_period_end?->toISOString(),
            ],
        ]);
    }
}
