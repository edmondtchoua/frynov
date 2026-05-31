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
            return response()->json(['message' => 'Identifiants invalides.'], 401);
        } catch (TenantInactiveException) {
            return response()->json(['message' => 'Compte suspendu ou inactif.'], 403);
        }

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

            // 3. Assign tenant-owner role
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
        return response()->json(['user' => new UserResource($request->user())]);
    }

    public function logout(Request $request): JsonResponse
    {
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
