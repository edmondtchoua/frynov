<?php

namespace App\Modules\Inventory\Support;

use App\Models\User;

/**
 * Resolves the effective warehouse filter for a listing request, combining:
 *  - the user's ACCESS restriction (user_warehouses assignments), and
 *  - the optional warehouse_id FILTER the user picked in the UI.
 *
 * Centralised so every list endpoint enforces site access the same way (no leaks).
 */
class WarehouseScope
{
    /**
     * @return array<int,string>|null  null  = no constraint (all warehouses in the tenant);
     *                                 array = constrain to these IDs ([] = deny all, e.g. the user
     *                                 requested a site they are not assigned to).
     */
    public static function resolve(User $user, ?string $requested): ?array
    {
        $requested   = ($requested === null || $requested === '') ? null : $requested;
        $restriction = $user->accessibleWarehouseIds();

        // Unrestricted user (admin/manager or no assignment): honour the optional filter only.
        if ($restriction === null) {
            return $requested === null ? null : [$requested];
        }

        // Restricted user: a specific request is allowed only if it is in their set.
        if ($requested !== null) {
            return in_array($requested, $restriction, true) ? [$requested] : [];
        }

        return $restriction;
    }
}
