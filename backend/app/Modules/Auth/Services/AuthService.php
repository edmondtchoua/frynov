<?php

namespace App\Modules\Auth\Services;

use App\Models\User;
use App\Modules\Auth\Exceptions\InvalidCredentialsException;
use App\Modules\Auth\Exceptions\TenantInactiveException;
use App\Modules\Auth\Repositories\UserRepositoryInterface;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
    ) {}

    /**
     * Authenticate a user and return a Sanctum token.
     *
     * @return array{user: User, token: string}
     */
    public function login(string $email, string $password, ?string $tenantId = null): array
    {
        $user = $this->users->findByEmail($email, $tenantId);

        if (! $user || ! $this->validateCredentials($user, $password)) {
            throw new InvalidCredentialsException();
        }

        if (! $this->canAccessTenant($user)) {
            throw new TenantInactiveException();
        }

        $token = $this->issueToken($user);

        return ['user' => $user, 'token' => $token];
    }

    public function logout(User $user): void
    {
        $user->currentAccessToken()->delete();
    }

    /** Pure business logic — testable without DB. */
    public function validateCredentials(User $user, string $password): bool
    {
        return Hash::check($password, $user->password);
    }

    /** Super admins bypass tenant checks. */
    public function canAccessTenant(User $user): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->tenant?->isActive() ?? false;
    }

    private function issueToken(User $user): string
    {
        // Single active session per user — revoke previous 'api' tokens.
        $user->tokens()->where('name', 'api')->delete();

        return $user->createToken('api', ['*'], now()->addDays(30))->plainTextToken;
    }
}
