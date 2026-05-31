<?php

namespace App\Modules\Platform\Models;

use App\Modules\Billing\Models\Plan;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ErpModule extends Model
{
    use HasUuids;

    protected $table = 'erp_modules';

    public const STATUS_ACTIVE       = 'active';
    public const STATUS_BETA         = 'beta';
    public const STATUS_COMING_SOON  = 'coming_soon';
    public const STATUS_MAINTENANCE  = 'maintenance';
    public const STATUS_DISABLED     = 'disabled';

    public const CATEGORY_CORE       = 'core';
    public const CATEGORY_OPERATIONS = 'operations';
    public const CATEGORY_FINANCE    = 'finance';
    public const CATEGORY_ANALYTICS  = 'analytics';
    public const CATEGORY_ADVANCED   = 'advanced';

    protected $fillable = [
        'code',
        'name',
        'category',
        'description',
        'icon_svg',
        'status',
        'is_core',
        'is_visible',
        'route_prefix',
        'color',
        'metadata',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_core'    => 'boolean',
            'is_visible' => 'boolean',
            'metadata'   => 'array',
            'sort_order' => 'integer',
        ];
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function isAvailable(): bool
    {
        return $this->status === self::STATUS_ACTIVE || $this->status === self::STATUS_BETA;
    }

    // ── Relations ─────────────────────────────────────────────────────────────

    public function plans(): BelongsToMany
    {
        return $this->belongsToMany(Plan::class, 'plan_modules', 'module_id', 'plan_id')
            ->withPivot(['is_included', 'limits']);
    }

    public function tenantModules(): HasMany
    {
        return $this->hasMany(TenantModule::class, 'module_id');
    }
}
