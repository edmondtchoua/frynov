<?php

namespace App\Modules\Billing\Models;

use App\Modules\Platform\Models\ErpModule;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Plan extends Model
{
    use HasUuids;

    public const CODE_STARTER = 'starter';      // public name: Découverte

    public const CODE_ESSENTIAL = 'essential';

    public const CODE_PRO = 'pro';          // public name: Croissance

    public const CODE_ENTERPRISE = 'enterprise';   // public name: Business / Enterprise

    // P5 — cycle de vie éditorial (back-office).
    public const STATUS_ACTIVE   = 'active';
    public const STATUS_DRAFT    = 'draft';
    public const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'code',
        'name',
        'description',
        'status',
        'badge',
        'tax_rate_bps',
        'setup_fee_minor',
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
            'price_monthly_cents' => 'integer',
            'price_yearly_cents' => 'integer',
            'max_users' => 'integer',
            'max_products' => 'integer',
            'max_monthly_orders' => 'integer',
            'max_agents' => 'integer',
            'max_branches' => 'integer',
            'max_warehouses' => 'integer',
            'trial_days' => 'integer',
            'tax_rate_bps' => 'integer',
            'setup_fee_minor' => 'integer',
            'features' => 'array',
            'is_active' => 'boolean',
            'is_public' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function isFree(): bool
    {
        return $this->price_monthly_cents === 0;
    }

    /** P5 — sélectionnable par un tenant : publié, actif ET au statut `active`. */
    public function isSelectableByTenant(): bool
    {
        return $this->is_active && $this->is_public && $this->status === self::STATUS_ACTIVE;
    }

    /** Scope des plans sélectionnables côté tenant (public pricing / devis). */
    public function scopeSelectable($query)
    {
        return $query->where('is_active', true)->where('is_public', true)->where('status', self::STATUS_ACTIVE);
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

    public function prices(): HasMany
    {
        return $this->hasMany(PlanPrice::class)->orderBy('sort_order');
    }

    public function limits(): HasOne
    {
        return $this->hasOne(PlanLimit::class);
    }

    public function priceForMarket(string $marketCode, string $interval = 'monthly'): ?PlanPrice
    {
        return $this->prices()
            ->where('market_code', $marketCode)
            ->where('interval', $interval)
            ->first()
            ?? $this->prices()
                ->where('market_code', 'global')
                ->where('interval', $interval)
                ->first();
    }
}
