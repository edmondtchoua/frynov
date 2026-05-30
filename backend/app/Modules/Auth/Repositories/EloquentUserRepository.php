<?php

namespace App\Modules\Auth\Repositories;

use App\Models\User;

class EloquentUserRepository implements UserRepositoryInterface
{
    public function findByEmail(string $email, ?string $tenantId = null): ?User
    {
        return User::query()
            ->where('email', $email)
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->with('tenant')
            ->first();
    }

    public function create(array $attributes): User
    {
        return User::create($attributes);
    }
}
