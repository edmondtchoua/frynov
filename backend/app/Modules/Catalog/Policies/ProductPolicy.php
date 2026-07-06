<?php

namespace App\Modules\Catalog\Policies;

use App\Models\User;
use App\Shared\Authorization\ModulePolicy;

/**
 * 2ᵉ ligne de défense (audit RBAC P2).
 *
 * ⚠️ Le groupe de routes catalogue partage UN SEUL middleware OR pour toutes les écritures
 * (`manager|admin|products.create|products.update|products.delete|products.archive`). Pour ne pas
 * DURCIR l'accès (un rôle custom porteur d'une seule de ces permissions pourrait aujourd'hui atteindre
 * toutes les écritures produit), cette policy MIROIR ce comportement grossier : toute écriture passe si
 * l'utilisateur est manager/admin ou détient l'une des permissions d'écriture produit. Purement
 * redondant avec la route — aucun changement d'accès effectif.
 */
class ProductPolicy extends ModulePolicy
{
    protected string $module = 'products';

    /** Permissions d'écriture partagées par le groupe de routes catalogue. */
    private const WRITE_PERMISSIONS = [
        'products.create',
        'products.update',
        'products.delete',
        'products.archive',
    ];

    protected function allows(User $user, string $action): bool
    {
        // Lecture : permission fine standard (`products.view`).
        if ($action === 'view') {
            return parent::allows($user, $action);
        }

        // Écritures : miroir du groupe de routes (OR sur toutes les permissions d'écriture).
        if ($user->hasAnyRole(['admin', 'manager'])) {
            return true;
        }

        foreach (self::WRITE_PERMISSIONS as $permission) {
            if ($user->can($permission)) {
                return true;
            }
        }

        return false;
    }
}
