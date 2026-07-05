<?php

namespace App\Modules\Auth\Http\Controllers;

use App\Modules\Auth\Services\TenantRoleService;
use App\Modules\Platform\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Spatie\Permission\Models\Role;

/**
 * Tenant-admin management of custom roles (RBAC Phase B2). Tenant-admin only.
 * A tenant may only create/edit/delete its OWN custom roles, and may only grant
 * permissions allowed by its plan + active modules (bounded by TenantRoleService).
 */
class TenantRoleController extends Controller
{
    public function __construct(
        private readonly TenantRoleService $service,
        private readonly AuditService $audit,
    ) {}

    /** GET /api/workspace/roles — base + custom roles + the grantable permission catalogue */
    public function index(Request $request): JsonResponse
    {
        $this->authorizeManageRoles($request);
        $tenant = $request->user()->tenant;

        return response()->json([
            'data'      => $this->service->listForTenant($tenant),
            'grantable' => $this->service->grantablePermissions($tenant),
        ]);
    }

    /** POST /api/workspace/roles */
    public function store(Request $request): JsonResponse
    {
        $this->authorizeManageRoles($request);
        $tenant = $request->user()->tenant;
        $data   = $this->validateData($request, $tenant, null);

        $role = $this->service->create($tenant, $data['name'], $data['permissions'] ?? []);
        $this->logAcl($request, 'role_created', $role);

        return response()->json($this->present($role), 201);
    }

    /** PATCH /api/workspace/roles/{role} */
    public function update(Request $request, Role $role): JsonResponse
    {
        $this->authorizeManageRoles($request);
        $tenant = $request->user()->tenant;
        $this->assertOwned($tenant, $role);
        $data = $this->validateData($request, $tenant, $role);

        $updated = $this->service->update($tenant, $role, $data['name'] ?? null, $data['permissions'] ?? null);
        $this->logAcl($request, 'role_updated', $updated);

        return response()->json($this->present($updated));
    }

    /** DELETE /api/workspace/roles/{role} */
    public function destroy(Request $request, Role $role): JsonResponse
    {
        $this->authorizeManageRoles($request);
        $tenant = $request->user()->tenant;
        $this->assertOwned($tenant, $role);

        $this->logAcl($request, 'role_deleted', $role);
        $this->service->delete($role);

        return response()->json(['message' => 'Rôle supprimé.']);
    }

    // ── helpers ───────────────────────────────────────────────────────────────

    private function authorizeManageRoles(Request $request): void
    {
        // Role management is tenant-admin only (it is an escalation surface).
        abort_unless((bool) $request->user()?->hasRole('admin'), 403, 'Action réservée aux administrateurs.');
    }

    private function assertOwned(object $tenant, Role $role): void
    {
        abort_unless($this->service->ownsCustomRole($tenant, $role), 403, 'Ce rôle n’est pas modifiable.');
    }

    /** @return array<string, mixed> */
    private function validateData(Request $request, object $tenant, ?Role $role): array
    {
        return $request->validate([
            'name' => [$role ? 'sometimes' : 'required', 'string', 'max:50', 'regex:/^[\p{L}0-9 _\-]+$/u',
                function ($attr, $value, $fail) use ($tenant, $role) {
                    $clash = Role::where('guard_name', 'web')
                        ->whereRaw('LOWER(name) = ?', [mb_strtolower($value)])
                        ->where(fn ($q) => $q->whereNull('tenant_id')->orWhere('tenant_id', $tenant->id))
                        ->when($role, fn ($q) => $q->where('id', '!=', $role->id))
                        ->exists();
                    if ($clash) {
                        $fail('Un rôle porte déjà ce nom.');
                    }
                },
            ],
            'permissions'   => ['sometimes', 'array'],
            'permissions.*' => ['string'],
        ]);
    }

    /** @return array<string, mixed> */
    private function present(Role $role): array
    {
        return [
            'id'          => $role->id,
            'name'        => $role->name,
            'is_custom'   => true,
            'permissions' => $role->permissions()->pluck('name')->values(),
        ];
    }

    private function logAcl(Request $request, string $action, Role $role): void
    {
        try {
            $this->audit->logFromRequest($request, 'workspace.' . $action, $role, [], ['role' => $role->name], 'medium');
        } catch (\Throwable) {
        }
    }
}
