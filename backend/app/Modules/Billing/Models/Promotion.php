<?php

namespace App\Modules\Billing\Models;

use App\Modules\Tenants\Models\Tenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Promotion extends Model
{
    use HasUuids;

    protected $fillable = [
        'code',
        'description',
        'discount_type',
        'discount_value',
        'applicable_plans',
        'valid_from',
        'valid_until',
        'max_uses',
        'current_uses',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'discount_value'    => 'integer',
            'applicable_plans'  => 'array',
            'valid_from'        => 'datetime',
            'valid_until'       => 'datetime',
            'max_uses'          => 'integer',
            'current_uses'      => 'integer',
            'is_active'         => 'boolean',
        ];
    }

    // ── Relations ─────────────────────────────────────────────────────────────

    public function uses(): HasMany
    {
        return $this->hasMany(PromoUse::class);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function isExpired(): bool
    {
        return $this->valid_until !== null && $this->valid_until->isPast();
    }

    public function hasNotStarted(): bool
    {
        return $this->valid_from !== null && $this->valid_from->isFuture();
    }

    public function isUsageLimitReached(): bool
    {
        return $this->max_uses !== null && $this->current_uses >= $this->max_uses;
    }

    public function appliesToPlan(string $planCode): bool
    {
        if ($this->applicable_plans === null) {
            return true; // applies to all plans
        }

        return in_array($planCode, $this->applicable_plans, true);
    }

    /**
     * Calculate the discounted amount in cents.
     */
    public function applyDiscount(int $originalCents): int
    {
        if ($this->discount_type === 'percent') {
            $discount = (int) round($originalCents * ($this->discount_value / 100));
        } else {
            $discount = $this->discount_value;
        }

        return max(0, $originalCents - $discount);
    }
}
