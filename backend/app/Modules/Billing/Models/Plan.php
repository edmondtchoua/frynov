<?php

namespace App\Modules\Billing\Models;

use App\Modules\Platform\Models\ErpModule;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    use HasUuids;

    public const CODE_STARTER    = 'starter';
    public const CODE_PRO        = 'pro';
    public const CODE_ENTERPRISE = 'enterprise';

    protected $fillable = [
        'code',
        'name',
        'description',
        'price_monthly_cents',
        'price_yearly_cents',
        'currency',
        'max_users',
        'max_products',
        'max_monthly_orders',
        'max_agents',
        'max_branches',
        'max_warehouses',
        'trial_days',
        'features',
        'is_active',
        'is_public',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price_monthly_cents'  => 'integer',
            'price_yearly_cents'   => 'integer',
            'max_users'            => 'integer',
            'max_products'         => 'integer',
            'max_monthly_orders'   => 'integer',
            'max_agents'           => 'integer',
            'max_branches'         => 'integer',
            'max_warehouses'       => 'integer',
            'trial_days'           => 'integer',
            'features'             => 'array',
            'is_active'            => 'boolean',
            'is_public'            => 'boolean',
            'sort_order'           => 'integer',
        ];
    }

    public function isFree(): bool
    {
        return $this->price_monthly_cents === 0;
    }

    // ── Relations ─────────────────────────────────────────────────────────────

    public function modules(): BelongsToMany
    {
        return $this->belongsToMany(ErpModule::class, 'plan_modules', 'plan_id', 'module_id')
            ->withPivot(['is_included', 'limits']);
    }

    public function includedModules(): BelongsToMany
    {
        return $this->modules()->wherePivot('is_included', true);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }
}
