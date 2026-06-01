<?php

namespace App\Modules\Auth\Http\Controllers;

use App\Modules\Auth\Exceptions\InvalidCredentialsException;
use App\Modules\Auth\Exceptions\TenantInactiveException;
use App\Modules\Auth\Http\Requests\LoginRequest;
use App\Modules\Auth\Http\Requests\RegisterRequest;
use App\Modules\Auth\Http\Resources\UserResource;
use App\Modules\Auth\Repositories\UserRepositoryInterface;
use App\Modules\Auth\Services\AuthService;
use App\Modules\Billing\Services\SubscriptionService;
use App\Modules\Platform\Services\AuditService;
use App\Modules\Platform\Services\ModuleRegistryService;
use App\Modules\Tenants\Services\TenantProvisioningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly UserRepositoryInterface $users,
        private readonly TenantProvisioningService $provisioner,
        private readonly SubscriptionService $subscriptions,
        private readonly ModuleRegistryService $moduleRegistry,
        private readonly AuditService $audit,
    ) {}

    public function login(LoginRequest $request): JsonResponse
    {
        try {
            ['user' => $user, 'token' => $token] = $this->authService->login(
                $request->input('email'),
                $request->input('password'),
                $request->input('tenant_id') ?? $request->attributes->get('tenant')?->id,
            );
        } catch (InvalidCredentialsException) {
            $this->audit->log(
                'auth.login_failed',
                null,
                null,
                null,
                null,
                ['email' => $request->email, 'ip' => $request->ip()],
                'medium',
            );

            return response()->json(['message' => 'Identifiants invalides.'], 401);
        } catch (TenantInactiveException) {
            return response()->json(['message' => 'Compte suspendu ou inactif.'], 403);
        }

        $this->audit->logFromRequest($request, 'auth.login', $user, [], [
            'email'     => $user->email,
            'tenant_id' => $user->tenant_id,
        ], 'low');

        return response()->json([
            'token' => $token,
            'user'  => new UserResource($user),
        ]);
    }

    /**
     * Register a new tenant + admin user in one atomic transaction.
     * Steps:
     *  1. Provision a new tenant (name, slug, default settings)
     *  2. Create the admin user linked to that tenant
     *  3. Assign the 'admin' role (tenant owner)
     *  4. Create a trialing subscription (starter plan + activate included modules)
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $result = DB::transaction(function () use ($request) {
            // 1. Provision tenant
            $tenant = $this->provisioner->provision([
                'name' => $request->input('company_name'),
            ]);

            // 2. Create admin user
            $user = $this->users->create([
                'name'      => $request->input('name'),
                'email'     => $request->input('email'),
                'password'  => $request->input('password'),
                'tenant_id' => $tenant->id,
            ]);

            // 3. Assign tenant-owner role (scoped to the new tenant via Spatie teams)
            app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
            $user->assignRole('admin');

            // 4. Create trialing subscription (starter plan + module activation)
            $this->subscriptions->createStarter($tenant);

            return $user;
        });

        $token = $result->createToken('api', ['*'], now()->addDays(30))->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => new UserResource($result->load('tenant')),
        ], 201);
    }

    public function me(Request $request): JsonResponse
    {
        $user   = $request->user()->load('tenant');
        $tenant = $user->tenant;

        $subscription  = null;
        $activeModules = [];

        if ($tenant) {
            $sub = $this->subscriptions->current($tenant)?->load('plan');
            if ($sub) {
                $subscription = [
                    'id'                 => $sub->id,
                    'plan_code'          => $sub->plan?->code ?? $tenant->plan,
                    'plan_name'          => $sub->plan?->name ?? ucfirst((string) $tenant->plan),
                    'status'             => $sub->status,
                    'trial_ends_at'      => $sub->trial_ends_at?->toISOString(),
                    'current_period_end' => $sub->current_period_end?->toISOString(),
                ];
            }
            $activeModules = $this->moduleRegistry->activeCodes($tenant);
        }

        $userData                     = (new UserResource($user))->toArray($request);
        $userData['subscription']     = $subscription;
        $userData['active_modules']   = $activeModules;

        return response()->json(['user' => $userData]);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->audit->logFromRequest($request, 'auth.logout', $request->user(), [], [], 'low');

        $this->authService->logout($request->user());

        return response()->json(['message' => 'Déconnexion réussie.']);
    }

    public function refresh(Request $request): JsonResponse
    {
        $user  = $request->user();
        $user->tokens()->where('name', 'api')->delete();
        $token = $user->createToken('api', ['*'], now()->addDays(30))->plainTextToken;

        return response()->json(['token' => $token]);
    }
}
