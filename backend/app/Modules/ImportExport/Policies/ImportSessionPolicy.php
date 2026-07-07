<?php

namespace App\Modules\ImportExport\Policies;

use App\Shared\Authorization\ModulePolicy;

/**
 * 2ᵉ ligne de défense (audit RBAC P2). Miroir exact des routes import :
 * `upload` → `import_export.create` ; `updateMapping`/`cancel` → `import_export.update`.
 */
class ImportSessionPolicy extends ModulePolicy
{
    protected string $module = 'import_export';
}
