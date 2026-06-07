<?php

namespace App\Modules\Auth\Support;

use App\Models\User;

/**
 * Central authority for "which base roles may an actor assign to other users".
 *
 * Security (audit remediation): prevents lateral/upward escalation —
 *   admin   → may assign admin, manager and every operational role;
 *   manager → may assign operational roles only, NOT manager and NOT admin;
 *   others  → may assign nothing.
 *
 * `assignableBaseRoles()` is used when changing an existing user's role.
 * `grantableBaseRoles()` additionally forbids `admin` — used for invitations and
 * temporary-access grants (you never invite/temporarily elevate someone to admin).
 */
final class RoleHierarchy
{
    /** @var array<string, list<string>> */
    private const ASSIGNABLE = [
        'admin'   => ['admin', 'manager', 'member', 'viewer', 'agent', 'cashier', 'commercial', 'delivery'],
        'manager' => ['member', 'viewer', 'agent', 'cashier', 'commercial', 'delivery'],
    ];

    /** Base roles the actor may set on an existing user. @return list<string> */
    public static function assignableBaseRoles(User $actor): array
    {
        if ($actor->isSuperAdmin() || $actor->hasRole('admin')) {
            return self::ASSIGNABLE['admin'];
        }
        if ($actor->hasRole('manager')) {
            return self::ASSIGNABLE['manager'];
        }

        return [];
    }

    /** Base roles the actor may hand out via invitation / temporary grant (never admin). @return list<string> */
    public static function grantableBaseRoles(User $actor): array
    {
        return array_values(array_diff(self::assignableBaseRoles($actor), ['admin']));
    }

    /** May the actor change an existing user TO this role? */
    public static function canAssign(User $actor, string $role): bool
    {
        return in_array($role, self::assignableBaseRoles($actor), true);
    }

    /** May the actor invite / temporarily grant this role? */
    public static function canGrant(User $actor, string $role): bool
    {
        return in_array($role, self::grantableBaseRoles($actor), true);
    }
}
