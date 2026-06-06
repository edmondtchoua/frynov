<?php

namespace App\Modules\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a user role/name update with role hierarchy enforcement.
 *
 * Security: prevents privilege escalation — a manager cannot promote
 * someone to admin, and a member/viewer cannot change any roles.
 *
 * Role hierarchy:
 *   admin   → can assign: admin, manager, member, viewer
 *   manager → can assign: manager, member, viewer  (NOT admin)
 *   member  → cannot assign any role
 *   viewer  → cannot assign any role
 */
class UpdateUserRoleRequest extends FormRequest
{
    private const ROLE_HIERARCHY = [
        'admin'       => ['admin', 'manager', 'member', 'viewer', 'agent', 'cashier', 'commercial', 'delivery'],
        'manager'     => ['manager', 'member', 'viewer', 'agent', 'cashier', 'commercial', 'delivery'],
        'member'      => [],
        'viewer'      => [],
        'super-admin' => ['admin', 'manager', 'member', 'viewer', 'agent', 'cashier', 'commercial', 'delivery'],
    ];

    public function authorize(): bool
    {
        $user     = $this->user();
        $tenantId = $user->tenant_id;

        // Ensure the Spatie team context is set for this check.
        // ResolveTenant middleware sets it via X-Tenant-Slug, but as a safety net
        // we also set it here from the authenticated user's own tenant_id.
        if ($tenantId) {
            app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId($tenantId);
        }

        return $user->hasAnyRole(['admin', 'manager']);
    }

    public function rules(): array
    {
        $caller       = $this->user();
        $callerRole   = $caller->getRoleNames()->first() ?? 'viewer';
        $allowedRoles = self::ROLE_HIERARCHY[$callerRole] ?? [];

        // RBAC B2: custom tenant roles are assignable by admin/manager. They carry
        // only plan-bounded, non-escalating permissions (no admin.*, no role/billing
        // management), so they never breach the hierarchy guard.
        if ($caller->tenant_id && $caller->hasAnyRole(['admin', 'manager'])) {
            $custom = \Spatie\Permission\Models\Role::query()
                ->where('guard_name', 'web')
                ->where('tenant_id', $caller->tenant_id)
                ->pluck('name')
                ->all();
            $allowedRoles = array_values(array_unique([...$allowedRoles, ...$custom]));
        }

        return [
            'name' => 'sometimes|string|max:255',
            'role' => [
                'sometimes',
                'string',
                Rule::in($allowedRoles),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'role.in' => 'Vous ne disposez pas des droits suffisants pour attribuer ce rôle.',
        ];
    }

    protected function failedAuthorization(): never
    {
        throw new \Illuminate\Auth\Access\AuthorizationException(
            'Action réservée aux administrateurs et managers.'
        );
    }
}
