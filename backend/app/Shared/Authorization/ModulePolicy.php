<?php

namespace App\Shared\Authorization;

use App\Models\User;

/**
 * Politique d'autorisation RÉUTILISABLE par module (2ᵉ ligne de défense — audit RBAC P2).
 *
 * Les routes d'écriture sont déjà gardées par le middleware `role_or_permission:` ; cette politique
 * ajoute une vérification côté CONTRÔLEUR (`Gate::authorize(...)`) pour qu'un oubli de middleware sur
 * une nouvelle route ne laisse pas l'action ouverte. Convention identique au middleware :
 * `manager`/`admin` OU la permission `<module>.<action>` (scopée au tenant via Spatie teams).
 *
 * Sous-classe : définir `$module`. Les arguments de modèle éventuels (instance) sont ignorés — seule
 * la permission compte ; l'isolation tenant (IDOR) reste assurée par le TenantScope + les services.
 */
abstract class ModulePolicy
{
    /** Code du module (ex. 'suppliers'). */
    protected string $module = '';

    protected function allows(User $user, string $action): bool
    {
        return $user->hasAnyRole(['admin', 'manager']) || $user->can("{$this->module}.{$action}");
    }

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'view');
    }

    public function view(User $user): bool
    {
        return $this->allows($user, 'view');
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'create');
    }

    public function update(User $user): bool
    {
        return $this->allows($user, 'update');
    }

    public function delete(User $user): bool
    {
        return $this->allows($user, 'delete');
    }
}
