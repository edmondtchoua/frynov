<?php

namespace App\Modules\Auth\Http\Controllers;

use App\Modules\Auth\Exceptions\InvalidCredentialsException;
use App\Modules\Auth\Exceptions\TenantInactiveException;
use App\Modules\Auth\Http\Requests\LoginRequest;
use App\Modules\Auth\Http\Requests\RegisterRequest;
use App\Modules\Auth\Http\Resources\UserResource;
use App\Modules\Auth\Repositories\UserRepositoryInterface;
use App\Modules\Auth\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly UserRepositoryInterface $users,
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

    public function register(RegisterRequest $request): JsonResponse
    {
        $user = $this->users->create([
            'name'      => $request->input('name'),
            'email'     => $request->input('email'),
            'password'  => $request->input('password'),
            'tenant_id' => $request->input('tenant_id'),
        ]);

        $user->assignRole('member');

        $token = $user->createToken('api', ['*'], now()->addDays(30))->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => new UserResource($user),
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
