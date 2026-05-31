<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Module slugs — must match ErpModule::code values.
     */
    private const MODULES = [
        'dashboard',
        'catalog',
        'inventory',
        'orders',
        'customers',
        'payments',
        'delivery',
        'suppliers',
        'import_export',
        'reports',
    ];

    /**
     * Actions generated per module.
     */
    private const ACTIONS = ['view', 'create', 'update', 'delete', 'export'];

    /**
     * Cross-module permissions (not tied to a specific module).
     */
    private const GLOBAL_PERMISSIONS = [
        // Tenant / billing
        'tenants.view',
        'tenants.manage',
        'subscriptions.view',
        'subscriptions.manage',
        // Users
        'users.view',
        'users.create',
        'users.update',
        'users.delete',
        // Roles
        'roles.view',
        'roles.manage',
        // Modules
        'modules.view',
        'modules.activate',
        'modules.deactivate',
        // Admin (super-admin only at route level, but seeded for completeness)
        'admin.access',
        'admin.tenants',
        'admin.plans',
        'admin.modules',
        'admin.audit',
    ];

    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // ── 1. Build permission list ──────────────────────────────────────────

        $permissions = self::GLOBAL_PERMISSIONS;

        foreach (self::MODULES as $module) {
            foreach (self::ACTIONS as $action) {
                $permissions[] = "{$module}.{$action}";
            }
        }

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        // ── 2. Roles ──────────────────────────────────────────────────────────

        // super-admin: managed at is_super_admin flag level; no need for permissions
        $superAdmin = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);

        // admin (tenant owner): full access within the tenant
        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->syncPermissions($permissions);   // all permissions

        // manager: can do everything except user/role management and billing
        $manager = Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        $managerPerms = collect($permissions)
            ->reject(fn ($p) => str_starts_with($p, 'users.') ||
                                str_starts_with($p, 'roles.') ||
                                str_starts_with($p, 'subscriptions.manage') ||
                                str_starts_with($p, 'admin.') ||
                                str_starts_with($p, 'tenants.manage'))
            ->values()
            ->toArray();
        $manager->syncPermissions($managerPerms);

        // member: view + create + update on operational modules; no delete, no finance
        $member = Role::firstOrCreate(['name' => 'member', 'guard_name' => 'web']);
        $operationalModules = ['dashboard', 'catalog', 'inventory', 'orders', 'customers', 'delivery'];
        $memberPerms = [];
        foreach ($operationalModules as $mod) {
            $memberPerms[] = "{$mod}.view";
            $memberPerms[] = "{$mod}.create";
            $memberPerms[] = "{$mod}.update";
        }
        $member->syncPermissions($memberPerms);

        // viewer: read-only on all modules
        $viewer = Role::firstOrCreate(['name' => 'viewer', 'guard_name' => 'web']);
        $viewerPerms = collect($permissions)
            ->filter(fn ($p) => str_ends_with($p, '.view') || $p === 'dashboard.view')
            ->values()
            ->toArray();
        $viewer->syncPermissions($viewerPerms);

        $this->command->info('Roles and permissions seeded successfully.');
    }
}
