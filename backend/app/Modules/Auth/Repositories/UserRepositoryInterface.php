<?php

namespace App\Modules\Auth\Repositories;

use App\Models\User;

interface UserRepositoryInterface
{
    public function findByEmail(string $email, ?string $tenantId = null): ?User;

    public function create(array $attributes): User;
}
