<?php

namespace App\Modules\Auth\Http\Controllers;

use App\Models\User;
use App\Modules\Auth\Http\Requests\UpdateUserRoleRequest;
use App\Modules\Tenants\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
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
            'role'  => 'required|string|in:manager,member,viewer',
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
            $newRole = $request->input('role');

            // Guard: cannot demote the only admin
            if ($user->hasRole('admin') && $newRole !== 'admin') {
                $adminCount = User::where('tenant_id', $tenant->id)
                    ->whereHas('roles', fn ($q) => $q->where('name', 'admin'))
                    ->count();

                if ($adminCount <= 1) {
                    return response()->json([
                        'message' => 'Impossible de retirer le rôle admin au dernier administrateur.',
                    ], 422);
                }
            }

            // Scope role update to the tenant (Spatie teams)
            app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
            $user->syncRoles([$newRole]);
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

        return response()->json([
            'data'    => $this->userToArray($user->refresh()),
            'message' => $message,
        ]);
    }

    // ── Workspace settings ────────────────────────────────────────────────────

    /**
     * GET /api/workspace/settings
     * Return tenant profile and settings.
     */
    public function getSettings(Request $request): JsonResponse
    {
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
