<?php

namespace App\Modules\Auth\Services;

use App\Modules\Platform\Services\ModuleRegistryService;
use App\Modules\Tenants\Models\Tenant;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Custom per-tenant roles (RBAC Phase B2). Roles are Spatie roles scoped to the
 * tenant via the team key (`tenant_id`). Base roles (admin/manager/…) are global
 * (tenant_id NULL) and shared; a tenant only ever *creates/edits/deletes its own*
 * custom roles, and may only grant permissions that its plan + active modules allow.
 */
class TenantRoleService
{
    /** Platform / escalation permissions a tenant may NEVER grant to a custom role. */
    private const BLOCKED = [
        'admin.access', 'admin.tenants', 'admin.plans', 'admin.modules', 'admin.audit',
        'tenants.view', 'tenants.manage',
        'subscriptions.manage',
        'modules.activate', 'modules.deactivate',
        'roles.manage',
    ];

    /** Module/action permissions of these modules are only grantable when the module is active. */
    private const OPTIONAL_MODULES = ['delivery', 'suppliers', 'import_export', 'reports'];

    public function __construct(private readonly ModuleRegistryService $registry) {}

    /**
     * Permission names a tenant may assign to its custom roles, bounded by plan/modules.
     *
     * @return list<string>
     */
    public function grantablePermissions(Tenant $tenant): array
    {
        $active = $this->registry->activeCodes($tenant); // active module codes for this tenant

        return Permission::where('guard_name', 'web')
            ->orderBy('name')
            ->pluck('name')
            ->reject(fn (string $p) => in_array($p, self::BLOCKED, true))
            ->reject(function (string $p) use ($active) {
                foreach (self::OPTIONAL_MODULES as $module) {
                    if (str_starts_with($p, $module . '.') && ! in_array($module, $active, true)) {
                        return true; // optional module not active → its module.action perms not grantable
                    }
                }

                return false;
            })
            ->values()
            ->all();
    }

    /**
     * Base (global) roles + this tenant's custom roles. Excludes super-admin.
     */
    public function listForTenant(Tenant $tenant): Collection
    {
        return Role::query()
            ->where('guard_name', 'web')
            ->where('name', '!=', 'super-admin')
            ->where(fn ($q) => $q->whereNull('tenant_id')->orWhere('tenant_id', $tenant->id))
            ->with('permissions:id,name')
            ->orderBy('name')
            ->get()
            ->map(fn (Role $r) => [
                'id'          => $r->id,
                'name'        => $r->name,
                'is_custom'   => $r->tenant_id === $tenant->id,
                'permissions' => $r->permissions->pluck('name')->values(),
            ]);
    }

    /**
     * Create a custom role for the tenant with a permission set bounded by the plan.
     */
    public function create(Tenant $tenant, string $name, array $permissions): Role
    {
        $registrar = app(PermissionRegistrar::class);
        $prev      = $registrar->getPermissionsTeamId();
        $registrar->setPermissionsTeamId($tenant->id);

        try {
            $role = Role::create(['name' => $name, 'guard_name' => 'web']); // tenant_id set from team context
            $role->syncPermissions($this->boundedPermissions($tenant, $permissions));

            return $role;
        } finally {
            $registrar->setPermissionsTeamId($prev);
        }
    }

    public function update(Tenant $tenant, Role $role, ?string $name, ?array $permissions): Role
    {
        $registrar = app(PermissionRegistrar::class);
        $prev      = $registrar->getPermissionsTeamId();
        $registrar->setPermissionsTeamId($tenant->id);

        try {
            if ($name !== null) {
                $role->update(['name' => $name]);
            }
            if ($permissions !== null) {
                $role->syncPermissions($this->boundedPermissions($tenant, $permissions));
            }

            return $role->fresh('permissions');
        } finally {
            $registrar->setPermissionsTeamId($prev);
        }
    }

    public function delete(Role $role): void
    {
        $role->delete(); // Spatie detaches model_has_roles + role_has_permissions
    }

    /** True if the role is a custom role owned by this tenant (safe to edit/delete). */
    public function ownsCustomRole(Tenant $tenant, Role $role): bool
    {
        return $role->guard_name === 'web' && $role->tenant_id === $tenant->id;
    }

    /**
     * Intersect requested permissions with what the tenant may grant (defence in depth).
     *
     * @return list<string>
     */
    private function boundedPermissions(Tenant $tenant, array $requested): array
    {
        return array_values(array_intersect($requested, $this->grantablePermissions($tenant)));
    }
}
