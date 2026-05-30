<?php

return [
    'models' => [
        'permission' => Spatie\Permission\Models\Permission::class,
        'role'       => Spatie\Permission\Models\Role::class,
    ],

    'table_names' => [
        'roles'               => 'roles',
        'permissions'         => 'permissions',
        'model_has_permissions' => 'model_has_permissions',
        'model_has_roles'     => 'model_has_roles',
        'role_has_permissions' => 'role_has_permissions',
    ],

    'column_names' => [
        'role_pivot_key'       => null,
        'permission_pivot_key' => null,
        // UUIDs: use 'model_uuid' instead of default 'model_id' (bigint)
        'model_morph_key'      => 'model_uuid',
        'team_foreign_key'     => 'tenant_id',
    ],

    'register_permission_check_method' => true,

    'register_octane_reset_listener' => false,

    // Enable tenant-scoped permissions (each tenant has its own roles)
    'teams' => true,

    'display_permission_in_exception' => false,
    'display_role_in_exception'        => false,

    'enable_wildcard_permission' => false,

    'cache' => [
        'expiration_time' => \DateInterval::createFromDateString('24 hours'),
        'key'             => 'spatie.permission.cache',
        'store'           => 'default',
    ],
];
