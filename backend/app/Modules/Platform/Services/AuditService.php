<?php

namespace App\Modules\Platform\Services;

use App\Models\User;
use App\Modules\Platform\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditService
{
    /**
     * Log an action from an HTTP request context (captures IP + user-agent automatically).
     */
    public function logFromRequest(
        Request $request,
        string $action,
        ?Model $subject = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $notes = null,
    ): AuditLog {
        /** @var ?User $user */
        $user = $request->user();

        $actor     = auth()->user();
        $actorRole = $actor?->getRoleNames()->first() ?? 'system';

        return AuditLog::create([
            'user_id'      => $user?->id,
            'tenant_id'    => $user?->tenant_id,
            'actor_role'   => $actorRole,
            'action'       => $action,
            'subject_type' => $subject ? get_class($subject) : null,
            'subject_id'   => $subject?->getKey(),
            'old_values'   => $oldValues,
            'new_values'   => $newValues,
            'ip_address'   => $request->ip(),
            'user_agent'   => $request->userAgent(),
            'notes'        => $notes,
        ]);
    }

    /**
     * Log an action in a non-HTTP context (CLI, queue jobs, etc.).
     */
    public function log(
        string $action,
        ?string $tenantId = null,
        ?string $userId = null,
        ?Model $subject = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $notes = null,
        ?string $actorRole = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): AuditLog {
        return AuditLog::create([
            'user_id'      => $userId,
            'tenant_id'    => $tenantId,
            'actor_role'   => $actorRole ?? 'system',
            'action'       => $action,
            'subject_type' => $subject ? get_class($subject) : null,
            'subject_id'   => $subject?->getKey(),
            'old_values'   => $oldValues,
            'new_values'   => $newValues,
            'ip_address'   => $ipAddress ?? request()->ip(),
            'user_agent'   => $userAgent ?? request()->userAgent(),
            'notes'        => $notes,
        ]);
    }

    // ── Convenience helpers for common audit actions ───────────────────────────

    public function logCreated(Request $request, Model $model, ?string $notes = null): AuditLog
    {
        return $this->logFromRequest(
            $request,
            'created.' . class_basename($model),
            $model,
            null,
            $model->toArray(),
            $notes,
        );
    }

    public function logUpdated(Request $request, Model $model, array $original, ?string $notes = null): AuditLog
    {
        return $this->logFromRequest(
            $request,
            'updated.' . class_basename($model),
            $model,
            $original,
            $model->getDirty() ?: $model->toArray(),
            $notes,
        );
    }

    public function logDeleted(Request $request, Model $model, ?string $notes = null): AuditLog
    {
        return $this->logFromRequest(
            $request,
            'deleted.' . class_basename($model),
            $model,
            $model->toArray(),
            null,
            $notes,
        );
    }

    public function logLogin(Request $request, User $user): AuditLog
    {
        return $this->logFromRequest($request, 'auth.login', $user);
    }

    public function logLogout(Request $request, User $user): AuditLog
    {
        return $this->logFromRequest($request, 'auth.logout', $user);
    }

    public function logModuleActivated(Request $request, string $moduleCode, string $tenantId): AuditLog
    {
        return $this->logFromRequest(
            $request,
            'module.activated',
            notes: "Module: {$moduleCode}",
            newValues: ['module_code' => $moduleCode, 'tenant_id' => $tenantId],
        );
    }

    public function logModuleDeactivated(Request $request, string $moduleCode, string $tenantId): AuditLog
    {
        return $this->logFromRequest(
            $request,
            'module.deactivated',
            notes: "Module: {$moduleCode}",
            oldValues: ['module_code' => $moduleCode, 'tenant_id' => $tenantId],
        );
    }

    public function logPlanChanged(Request $request, string $tenantId, string $oldPlan, string $newPlan): AuditLog
    {
        return $this->logFromRequest(
            $request,
            'subscription.plan_changed',
            oldValues: ['plan' => $oldPlan],
            newValues: ['plan' => $newPlan],
            notes: "Tenant: {$tenantId}",
        );
    }
}
