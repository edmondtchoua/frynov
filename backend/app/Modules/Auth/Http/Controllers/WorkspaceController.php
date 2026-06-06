<?php

namespace App\Modules\Auth\Http\Controllers;

use App\Models\User;
use App\Modules\Auth\Http\Requests\UpdateUserRoleRequest;
use App\Modules\Tenants\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class WorkspaceController extends Controller
{
    // ── Users ──────────────────────────────────────────────────────────────────

    /**
     * GET /api/workspace/users
     * List all users (active + inactive) belonging to the authenticated tenant.
     */
    public function listUsers(Request $request): JsonResponse
    {
        // Sprint 11: restrict member/role enumeration to admin and manager
        if (!$request->user()->hasAnyRole(['admin', 'manager'])) {
            return response()->json(['message' => 'Accès réservé aux administrateurs et managers.'], 403);
        }

        $tenant = $request->user()->tenant;

        $users = User::withTrashed()
            ->where('tenant_id', $tenant->id)
            ->oldest()
            ->get()
            ->map(fn (User $u) => $this->userToArray($u));

        return response()->json(['data' => $users]);
    }

    /**
     * POST /api/workspace/users
     * Create a new user in the tenant and return a temporary password.
     * Requires admin or manager role.
     */
    public function inviteUser(Request $request): JsonResponse
    {
        if (! $request->user()->hasAnyRole(['admin', 'manager'])) {
            return response()->json(['message' => 'Action réservée aux administrateurs.'], 403);
        }

        $request->validate([
            'name'  => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'role'  => 'required|string|in:manager,member,viewer,agent,cashier,commercial,delivery',
        ]);

        $tenant      = $request->user()->tenant;
        $rawPassword = Str::random(10);

        $user = User::create([
            'name'      => $request->input('name'),
            'email'     => $request->input('email'),
            'password'  => Hash::make($rawPassword),
            'tenant_id' => $tenant->id,
        ]);
        // Scope role assignment to the tenant (Spatie teams)
        app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        $user->assignRole($request->input('role'));

        return response()->json([
            'data'          => $this->userToArray($user),
            'temp_password' => $rawPassword,
            'message'       => 'Utilisateur ajouté avec succès.',
        ], 201);
    }

    /**
     * PATCH /api/workspace/users/{userId}
     * Update a user's name and/or role.
     * Uses UpdateUserRoleRequest which enforces role hierarchy (no privilege escalation).
     */
    public function updateUser(UpdateUserRoleRequest $request, string $userId): JsonResponse
    {
        $tenant = $request->user()->tenant;
        $user   = User::withTrashed()
            ->where('tenant_id', $tenant->id)
            ->findOrFail($userId);
        // Validation already done by UpdateUserRoleRequest (authorize + rules)

        if ($request->has('name')) {
            $user->update(['name' => $request->input('name')]);
        }

        if ($request->has('role')) {
            $newRole  = $request->input('role');
            $newRoles = [$newRole];

            DB::transaction(function () use ($user, $newRole, $newRoles, $tenant, $request, &$response) {
                // Guard: cannot demote the only admin
                if ($user->hasRole('admin') && $newRole !== 'admin') {
                    $adminCount = User::where('tenant_id', $tenant->id)
                        ->whereHas('roles', fn ($q) => $q->where('name', 'admin'))
                        ->count();

                    if ($adminCount <= 1) {
                        $response = response()->json([
                            'message' => 'Impossible de retirer le rôle admin au dernier administrateur.',
                        ], 422);
                        return;
                    }
                }

                $oldRoles = $user->getRoleNames();

                // Scope role update to the tenant (Spatie teams)
                app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
                $user->syncRoles($newRoles);

                try {
                    app(\App\Modules\Platform\Services\AuditService::class)->logFromRequest(
                        $request,
                        'workspace.role_changed',
                        $user,
                        ['old_roles' => $oldRoles],
                        ['new_roles' => $newRoles, 'changed_by' => $request->user()->id],
                        'medium',
                    );
                } catch (\Throwable) {}
            });

            if (isset($response)) {
                return $response;
            }
        }

        return response()->json(['data' => $this->userToArray($user->refresh())]);
    }

    /**
     * DELETE /api/workspace/users/{userId}
     * Toggle user activation. Deactivates active users (soft delete), reactivates
     * soft-deleted ones. Cannot target yourself or the last admin.
     */
    public function toggleUser(Request $request, string $userId): JsonResponse
    {
        if (! $request->user()->hasAnyRole(['admin', 'manager'])) {
            return response()->json(['message' => 'Action réservée aux administrateurs.'], 403);
        }

        $tenant = $request->user()->tenant;
        $user   = User::withTrashed()
            ->where('tenant_id', $tenant->id)
            ->findOrFail($userId);

        // Sprint 11: prevent rank escalation — managers cannot deactivate admins
        if ($user->hasRole('admin') && !$request->user()->hasRole('admin')) {
            return response()->json(['message' => 'Un manager ne peut pas désactiver un administrateur.'], 403);
        }

        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'Vous ne pouvez pas modifier votre propre statut.'], 422);
        }

        // Guard: cannot deactivate the last admin
        if (! $user->trashed() && $user->hasRole('admin')) {
            $adminCount = User::where('tenant_id', $tenant->id)
                ->whereHas('roles', fn ($q) => $q->where('name', 'admin'))
                ->count();

            if ($adminCount <= 1) {
                return response()->json(['message' => 'Impossible de désactiver le dernier administrateur.'], 422);
            }
        }

        if ($user->trashed()) {
            $user->restore();
            $message = 'Utilisateur réactivé.';
        } else {
            $user->delete(); // soft delete — blocks login
            $message = 'Utilisateur désactivé.';
        }

        try {
            app(\App\Modules\Platform\Services\AuditService::class)->logFromRequest(
                $request,
                $user->trashed() ? 'workspace.user_deactivated' : 'workspace.user_activated',
                $user,
                [],
                ['toggled_by' => $request->user()->id],
                'medium',
            );
        } catch (\Throwable) {}

        return response()->json([
            'data'    => $this->userToArray($user->refresh()),
            'message' => $message,
        ]);
    }

    /**
     * PUT /api/workspace/users/{userId}/warehouses
     * Set the warehouses a (non-manager) user's data access is scoped to.
     * Empty list = unrestricted (whole tenant). Admin/manager only.
     */
    public function setUserWarehouses(Request $request, string $userId): JsonResponse
    {
        if (! $request->user()->hasAnyRole(['admin', 'manager'])) {
            return response()->json(['message' => 'Action réservée aux administrateurs et managers.'], 403);
        }

        $tenant = $request->user()->tenant;
        $user   = User::where('tenant_id', $tenant->id)->findOrFail($userId);

        $request->validate([
            'warehouse_ids'   => ['present', 'array'],
            'warehouse_ids.*' => ['uuid'],
        ]);

        // Keep only warehouses that actually belong to this tenant (no cross-tenant assignment).
        $validIds = \App\Modules\Inventory\Models\Warehouse::where('tenant_id', $tenant->id)
            ->whereIn('id', $request->input('warehouse_ids'))
            ->pluck('id')
            ->all();

        DB::transaction(function () use ($user, $validIds, $tenant) {
            DB::table('user_warehouses')->where('user_id', $user->id)->delete();
            foreach ($validIds as $wid) {
                DB::table('user_warehouses')->insert([
                    'id'           => (string) Str::uuid(),
                    'user_id'      => $user->id,
                    'warehouse_id' => $wid,
                    'tenant_id'    => $tenant->id,
                    'role'         => 'operator',
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]);
            }
        });

        try {
            app(\App\Modules\Platform\Services\AuditService::class)->logFromRequest(
                $request, 'workspace.warehouses_assigned', $user, [], ['warehouse_ids' => $validIds], 'medium',
            );
        } catch (\Throwable) {}

        return response()->json([
            'data'    => $this->userToArray($user->refresh()),
            'message' => 'Accès aux entrepôts mis à jour.',
        ]);
    }

    // ── Onboarding provisioning ───────────────────────────────────────────────

    /**
     * POST /api/workspace/provision
     * Called at the end of the onboarding wizard to provision the workspace.
     * Sprint 11 GO item — cables the cosmetic onboarding to the backend.
     */
    public function provision(Request $request): JsonResponse
    {
        if (!$request->user()->hasAnyRole(['admin', 'manager'])) {
            return response()->json(['message' => 'Action reservee aux administrateurs et managers.'], 403);
        }

        $data = $request->validate([
            'company_name'    => ['required', 'string', 'max:150'],
            'country'         => ['required', 'string', 'size:2'],
            'currency'        => ['required', 'string', 'size:3'],
            'timezone'        => ['nullable', 'string', 'max:50'],
            'sector'          => ['nullable', 'string', 'max:80'],
            'activity_type'   => ['nullable', 'string', 'max:80'],
            'nb_users'        => ['nullable', 'integer', 'min:1'],
            'nb_branches'     => ['nullable', 'integer', 'min:1'],
            'needs_stock'     => ['boolean'],
            'needs_pos'       => ['boolean'],
            'needs_delivery'  => ['boolean'],
            'needs_ecommerce' => ['boolean'],
            'needs_offline'   => ['boolean'],
            'modules'         => ['nullable', 'array'],
            'warehouse_name'  => ['nullable', 'string', 'max:100'],
        ]);

        $tenant = $request->user()->tenant;

        // Store enriched settings from onboarding.
        // currency/country/timezone are collected & validated above — they MUST be
        // persisted (the order/invoice currency reads tenant->settings['currency']).
        // They were previously dropped, so onboarding's currency choice had no effect.
        $settings = array_merge($tenant->settings ?? [], [
            'currency'        => $data['currency'],
            'country'         => $data['country'],
            'timezone'        => $data['timezone'] ?? ($tenant->settings['timezone'] ?? 'Africa/Abidjan'),
            'sector'          => $data['sector'] ?? null,
            'activity_type'   => $data['activity_type'] ?? null,
            'nb_users'        => $data['nb_users'] ?? null,
            'nb_branches'     => $data['nb_branches'] ?? null,
            'needs_stock'     => $data['needs_stock'] ?? false,
            'needs_pos'       => $data['needs_pos'] ?? false,
            'needs_delivery'  => $data['needs_delivery'] ?? false,
            'needs_ecommerce' => $data['needs_ecommerce'] ?? false,
            'needs_offline'   => $data['needs_offline'] ?? false,
            'onboarding_completed_at' => now()->toIso8601String(),
        ]);

        $tenant->update([
            'name'     => $data['company_name'],
            'settings' => $settings,
        ]);

        // Create default warehouse if name provided
        if (!empty($data['warehouse_name'])) {
            \App\Modules\Inventory\Models\Warehouse::firstOrCreate(
                ['tenant_id' => $tenant->id, 'is_default' => true],
                ['name' => $data['warehouse_name'], 'code' => strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $data['warehouse_name']), 0, 6))]
            );
        }

        $tenant->update(['onboarded' => true]);

        return response()->json([
            'message'  => 'Espace de travail configuré avec succès.',
            'tenant'   => $tenant->fresh(),
            'user'     => $request->user(),
        ]);
    }

    // ── Workspace settings ────────────────────────────────────────────────────

    /**
     * GET /api/workspace/settings
     * Return tenant profile and settings.
     */
    public function getSettings(Request $request): JsonResponse
    {
        if (!$request->user()->hasAnyRole(['admin', 'manager'])) {
            return response()->json(['message' => 'Action reservee aux administrateurs et managers.'], 403);
        }

        return response()->json([
            'data' => $this->tenantToArray($request->user()->tenant),
        ]);
    }

    /**
     * PATCH /api/workspace/settings
     * Update tenant name, domain, and settings JSON.
     * Requires admin role.
     */
    public function updateSettings(Request $request): JsonResponse
    {
        if (! $request->user()->hasRole('admin')) {
            return response()->json(['message' => 'Action réservée aux administrateurs.'], 403);
        }

        $tenant = $request->user()->tenant;

        $request->validate([
            'name'              => 'sometimes|string|max:255',
            'domain'            => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
                Rule::unique('tenants', 'domain')
                    ->whereNotNull('domain')
                    ->ignore($tenant->id),
            ],
            'settings'          => 'sometimes|array',
            'settings.country'                 => 'sometimes|nullable|string|max:3',
            'settings.currency'                => 'sometimes|nullable|string|size:3',
            'settings.timezone'                => 'sometimes|nullable|string|max:50',
            'settings.phone'                   => 'sometimes|nullable|string|max:30',
            'settings.address'                 => 'sometimes|nullable|string|max:500',
            'settings.website'                 => 'sometimes|nullable|string|max:255',
            // Session timeout: 1 min → 43200 min (30 days). Default 1440 (24h).
            'settings.session_timeout_minutes' => 'sometimes|nullable|integer|min:1|max:43200',
        ]);

        if ($request->has('name'))   $tenant->name   = $request->input('name');
        if ($request->has('domain')) $tenant->domain = $request->input('domain') ?: null;

        if ($request->has('settings')) {
            $tenant->settings = array_merge($tenant->settings ?? [], $request->input('settings'));
        }

        $tenant->save();

        return response()->json([
            'data'    => $this->tenantToArray($tenant),
            'message' => 'Paramètres mis à jour.',
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function userToArray(User $user): array
    {
        return [
            'id'         => $user->id,
            'name'       => $user->name,
            'email'      => $user->email,
            'roles'      => $user->getRoleNames(),
            'is_active'  => $user->deleted_at === null,
            'created_at' => $user->created_at?->toISOString(),
            'warehouse_ids' => DB::table('user_warehouses')->where('user_id', $user->id)->pluck('warehouse_id')->all(),
        ];
    }

    private function tenantToArray(Tenant $tenant): array
    {
        return [
            'id'       => $tenant->id,
            'name'     => $tenant->name,
            'slug'     => $tenant->slug,
            'domain'   => $tenant->domain,
            'settings' => $tenant->settings ?? [],
        ];
    }
}
