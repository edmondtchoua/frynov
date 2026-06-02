<?php

namespace App\Modules\Billing\Http\Middleware;

use App\Modules\Billing\Exceptions\QuotaExceededException;
use App\Modules\Billing\Services\QuotaService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceQuota
{
    public function __construct(private readonly QuotaService $quotaService) {}

    /**
     * Reject the request with 402 Payment Required if the tenant has exhausted
     * the quota for $resource (passed as a route middleware parameter).
     *
     * Usage: ->middleware('quota:users')  |  'quota:products'  |  'quota:orders'
     */
    public function handle(Request $request, Closure $next, string $resource): Response
    {
        $tenant = $this->resolveTenant($request);

        if ($tenant !== null) {
            try {
                $this->quotaService->check($tenant, $resource);
            } catch (QuotaExceededException $e) {
                return response()->json([
                    'message'  => "Plan quota exceeded for [{$e->resource}]: limit is {$e->limit}, current usage is {$e->usage}. Please upgrade your plan.",
                    'error'    => 'quota_exceeded',
                    'resource' => $e->resource,
                    'limit'    => $e->limit,
                    'usage'    => $e->usage,
                ], Response::HTTP_PAYMENT_REQUIRED);
            } catch (\DomainException $e) {
                // assertCan*() methods throw \DomainException with a user-friendly message
                return response()->json([
                    'message' => $e->getMessage(),
                    'error'   => 'quota_exceeded',
                ], Response::HTTP_PAYMENT_REQUIRED);
            }
        }

        return $next($request);
    }

    private function resolveTenant(Request $request): mixed
    {
        // ResolveTenant middleware (global API group) binds this
        if (app()->bound('current.tenant') && ($tenant = app('current.tenant')) !== null) {
            return $tenant;
        }

        $user = $request->user();

        if ($user !== null && ! $user->isSuperAdmin()) {
            return $user->tenant;
        }

        return null;
    }
}
