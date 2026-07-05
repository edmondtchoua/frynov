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
        // Exact-match loop, not LIKE: "LIKE 'boutique%'" over-matched unrelated slugs
        // ("boutique-store") and the count suffix could collide after a mid-list delete.
        $base = Str::slug($name) ?: 'tenant';
        $slug = $base;
        $i = 1;

        while (Tenant::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
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
