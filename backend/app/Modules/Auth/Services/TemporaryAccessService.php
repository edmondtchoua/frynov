<?php

namespace App\Modules\Auth\Services;

use App\Models\User;
use App\Modules\Auth\Models\TemporaryAccessGrant;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Time-boxed access: grants a tenant role until an expiry, then revokes it
 * automatically (scheduled command). Grants only apply to a role the user does
 * NOT already hold, so expiry never strips a permanent role.
 */
class TemporaryAccessService
{
    public function grant(User $user, string $role, CarbonInterface $expiresAt, ?User $grantedBy = null, ?string $note = null): TemporaryAccessGrant
    {
        return DB::transaction(function () use ($user, $role, $expiresAt, $grantedBy, $note) {
            $user->assignTenantRole($role);

            return TemporaryAccessGrant::create([
                'tenant_id'  => $user->tenant_id,
                'user_id'    => $user->id,
                'role'       => $role,
                'granted_by' => $grantedBy?->id,
                'expires_at' => $expiresAt,
                'note'       => $note,
            ]);
        });
    }

    /** Revoke a grant: drop the role unless another active grant keeps it alive. */
    public function revoke(TemporaryAccessGrant $grant): void
    {
        DB::transaction(function () use ($grant) {
            $grant->forceFill(['revoked_at' => now()])->save();

            $keptByAnother = TemporaryAccessGrant::query()
                ->where('user_id', $grant->user_id)
                ->where('role', $grant->role)
                ->whereKeyNot($grant->getKey())
                ->active()
                ->exists();

            if (! $keptByAnother) {
                User::find($grant->user_id)?->removeTenantRole($grant->role);
            }
        });
    }

    /** Revoke every lapsed grant (the scheduler's work). Returns how many were revoked. */
    public function revokeExpired(): int
    {
        $due = TemporaryAccessGrant::query()->due()->get();

        foreach ($due as $grant) {
            $this->revoke($grant);
        }

        return $due->count();
    }
}
