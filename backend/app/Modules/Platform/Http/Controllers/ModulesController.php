<?php

namespace App\Modules\Platform\Http\Controllers;

use App\Modules\Platform\Services\ModuleRegistryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ModulesController extends Controller
{
    public function __construct(private readonly ModuleRegistryService $registry) {}

    /**
     * Returns all visible ERP modules with tenant_active status.
     * Used by the frontend to render the module dashboard and navigation.
     */
    public function forCurrentTenant(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        if (! $tenant) {
            return response()->json(['data' => [], 'active_codes' => []]);
        }

        $modules     = $this->registry->listForTenant($tenant);
        $activeCodes = $this->registry->activeCodes($tenant);

        return response()->json([
            'data'         => $modules,
            'active_codes' => $activeCodes,
        ]);
    }
}
