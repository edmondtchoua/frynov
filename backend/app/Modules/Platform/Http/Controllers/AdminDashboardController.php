<?php

namespace App\Modules\Platform\Http\Controllers;

use App\Models\User;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Platform\Models\AuditLog;
use App\Modules\Platform\Models\ErpModule;
use App\Modules\Platform\Models\TenantModule;
use App\Modules\Tenants\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class AdminDashboardController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $totalTenants     = Tenant::count();
        $activeTenants    = Tenant::where('status', 'active')->count();
        $suspendedTenants = Tenant::where('status', 'suspended')->count();
        $totalUsers       = User::whereNotNull('tenant_id')->count();

        $subscriptionStats = Subscription::selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $planStats = Subscription::join('plans', 'subscriptions.plan_id', '=', 'plans.id')
            ->selectRaw('plans.code, plans.name, count(*) as total')
            ->whereNotIn('subscriptions.status', [Subscription::STATUS_CANCELLED])
            ->groupBy('plans.id', 'plans.code', 'plans.name')
            ->get();

        $recentTenants = Tenant::latest()
            ->take(5)
            ->get(['id', 'name', 'slug', 'status', 'plan', 'subscription_status', 'created_at']);

        $recentAuditLogs = AuditLog::with('user:id,name,email')
            ->latest()
            ->take(10)
            ->get(['id', 'user_id', 'tenant_id', 'action', 'subject_type', 'subject_id', 'ip_address', 'created_at']);

        return response()->json([
            'overview' => [
                'tenants'          => $totalTenants,
                'active_tenants'   => $activeTenants,
                'suspended_tenants'=> $suspendedTenants,
                'total_users'      => $totalUsers,
                'total_modules'    => ErpModule::count(),
                'total_plans'      => Plan::where('is_active', true)->count(),
            ],
            'subscriptions'   => $subscriptionStats,
            'by_plan'         => $planStats,
            'recent_tenants'  => $recentTenants,
            'recent_logs'     => $recentAuditLogs,
        ]);
    }
}
