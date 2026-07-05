<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$tenant = App\Modules\Tenants\Models\Tenant::firstOrCreate(
    ['slug' => 'demo'],
    [
        'name'     => 'Société Demo',
        'plan'     => 'pro',
        'status'   => 'active',
        'settings' => ['currency' => 'XOF', 'timezone' => 'Africa/Abidjan', 'locale' => 'fr', 'order_prefix' => 'DEM'],
    ]
);

$user = App\Models\User::firstOrCreate(
    ['email' => 'admin@demo.com'],
    [
        'name'      => 'Admin Demo',
        'password'  => bcrypt('password'),
        'tenant_id' => $tenant->id,
    ]
);
if (!$user->hasRole('admin')) $user->assignRole('admin');

// Second user
$user2 = App\Models\User::firstOrCreate(
    ['email' => 'marie@demo.com'],
    [
        'name'      => 'Marie Dupont',
        'password'  => bcrypt('password'),
        'tenant_id' => $tenant->id,
    ]
);
if (!$user2->hasRole('member')) $user2->assignRole('member');

echo "Seeded: admin@demo.com / password\n";
echo "Tenant: {$tenant->name} (id={$tenant->id})\n";
