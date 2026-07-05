<?php

namespace App\Modules\Auth\Services;

use App\Modules\Tenants\Models\Tenant;
use Illuminate\Http\Request;

class TenantResolverService
{
    /**
     * Resolve the current tenant from the request.
     *
     * Resolution order:
     *   1. X-Tenant-ID header (UUID)
     *   2. X-Tenant-Slug header (slug string)
     *   3. Subdomain of the Host header (slug.domain.com)
     */
    public function resolve(Request $request): ?Tenant
    {
        if ($id = $request->header('X-Tenant-ID')) {
            return $this->findById($id);
        }

        if ($slug = $request->header('X-Tenant-Slug')) {
            return $this->findBySlug($slug);
        }

        if ($slug = $this->extractSubdomain($request)) {
            return $this->findBySlug($slug);
        }

        return null;
    }

    private function findById(string $id): ?Tenant
    {
        return Tenant::where('id', $id)->where('status', 'active')->first();
    }

    private function findBySlug(string $slug): ?Tenant
    {
        return Tenant::where('slug', $slug)->where('status', 'active')->first();
    }

    private function extractSubdomain(Request $request): ?string
    {
        $host  = $request->getHost();
        $parts = explode('.', $host);

        // Require at least 3 parts (subdomain.domain.tld)
        if (count($parts) < 3) {
            return null;
        }

        $subdomain = $parts[0];

        // Skip generic subdomains
        if (in_array($subdomain, ['www', 'api', 'app', 'staging'], true)) {
            return null;
        }

        return $subdomain;
    }
}
