<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

/**
 * Creates the super-admin account.
 * Idempotent — safe to run multiple times.
 *
 * Login: superadmin@frynov.com / Secret123!
 */
class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);

        // is_super_admin is NOT fillable — use forceFill() here since this is trusted seeder code
        $superAdmin = User::updateOrCreate(
            ['email' => 'superadmin@frynov.com'],
            [
                'name'      => 'Super Admin',
                'password'  => Hash::make('Secret123!'),
                'tenant_id' => null,
            ]
        );

        // forceFill bypasses $fillable — only used in trusted internal code (seeders, console commands)
        if (! $superAdmin->is_super_admin) {
            $superAdmin->forceFill(['is_super_admin' => true])->save();
        }

        $superAdmin->syncRoles(['super-admin']);

        $this->command->info('Super admin seeded: superadmin@frynov.com / Secret123!');
    }
}
