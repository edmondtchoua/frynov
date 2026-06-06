<?php

namespace App\Models;

use App\Modules\Tenants\Models\Tenant;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, HasUuids, Notifiable, SoftDeletes;

    /**
     * SECURITY: is_super_admin is intentionally NOT fillable.
     * Mass-assigning it via API payloads would be a critical privilege escalation.
     * Use promoteToSuperAdmin() or forceFill() from trusted internal code only.
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'tenant_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'is_super_admin'    => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function isSuperAdmin(): bool
    {
        return $this->is_super_admin === true;
    }

    /**
     * Promote this user to super-admin status.
     * Only callable from trusted internal code (seeders, console commands).
     * Never expose this via an API endpoint.
     */
    public function promoteToSuperAdmin(): void
    {
        $this->forceFill(['is_super_admin' => true])->save();
    }

    /**
     * Assign a role scoped to this user's tenant (Spatie teams=true).
     * Use this instead of assignRole() to ensure the role is stored with
     * the correct tenant_id in model_has_roles.
     */
    public function assignTenantRole(string ...$roles): static
    {
        $registrar = app(\Spatie\Permission\PermissionRegistrar::class);
        $prev      = $registrar->getPermissionsTeamId();

        if ($this->tenant_id) {
            $registrar->setPermissionsTeamId($this->tenant_id);
        }

        $this->assignRole(...$roles);

        $registrar->setPermissionsTeamId($prev);

        return $this;
    }

    /**
     * Sync roles scoped to this user's tenant (Spatie teams=true).
     */
    public function syncTenantRoles(array $roles): static
    {
        $registrar = app(\Spatie\Permission\PermissionRegistrar::class);
        $prev      = $registrar->getPermissionsTeamId();

        if ($this->tenant_id) {
            $registrar->setPermissionsTeamId($this->tenant_id);
        }

        $this->syncRoles($roles);

        $registrar->setPermissionsTeamId($prev);

        return $this;
    }
}
