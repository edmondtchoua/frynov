<?php

namespace App\Modules\Tenants\Services;

use App\Modules\Tenants\Models\Tenant;
use Illuminate\Support\Str;

class TenantProvisioningService
{
    public function provision(array $data): Tenant
    {
        $tenant = Tenant::create([
            'name'   => $data['name'],
            'slug'   => $this->generateSlug($data['name']),
            'plan'   => $data['plan'] ?? 'starter',
            'status' => 'active',
        ]);

        $this->createDefaultSettings($tenant);

        return $tenant;
    }

    private function generateSlug(string $name): string
    {
        $slug = Str::slug($name);
        $count = Tenant::where('slug', 'like', "{$slug}%")->count();

        return $count > 0 ? "{$slug}-{$count}" : $slug;
    }

    private function createDefaultSettings(Tenant $tenant): void
    {
        $tenant->update([
            'settings' => [
                'currency'     => 'XOF',
                'timezone'     => 'Africa/Abidjan',
                'locale'       => 'fr',
                'order_prefix' => strtoupper(substr($tenant->slug, 0, 3)),
            ],
        ]);
    }
}
