<?php

namespace App\Modules\Billing\Models;

use App\Modules\Tenants\Models\Tenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Subscription extends Model
{
    use HasUuids, SoftDeletes;

    public const STATUS_TRIALING           = 'trialing';
    public const STATUS_ACTIVE             = 'active';
    public const STATUS_PAST_DUE           = 'past_due';
    public const STATUS_SUSPENDED          = 'suspended';
    public const STATUS_CANCELLED          = 'cancelled';
    public const STATUS_PENDING_APPROVAL   = 'pending_approval';

    protected $fillable = [
        'tenant_id',
        'plan_id',
        'status',
        'trial_ends_at',
        'current_period_start',
        'current_period_end',
        'cancelled_at',
        'cancellation_reason',
        'approved_by',
        'approved_at',
        'notes',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'trial_ends_at'         => 'datetime',
            'current_period_start'  => 'datetime',
            'current_period_end'    => 'datetime',
            'cancelled_at'          => 'datetime',
            'approved_at'           => 'datetime',
            'metadata'              => 'array',
        ];
    }

    // ── State helpers ─────────────────────────────────────────────────────────

    public function isActive(): bool
    {
        return in_array($this->status, [self::STATUS_ACTIVE, self::STATUS_TRIALING], true);
    }

    public function isTrialing(): bool
    {
        return $this->status === self::STATUS_TRIALING
            && $this->trial_ends_at
            && $this->trial_ends_at->isFuture();
    }

    public function isSuspended(): bool
    {
        return $this->status === self::STATUS_SUSPENDED;
    }

    public function daysUntilTrialEnd(): ?int
    {
        if (! $this->isTrialing()) return null;
        return (int) now()->diffInDays($this->trial_ends_at, false);
    }

    // ── Relations ─────────────────────────────────────────────────────────────

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }
}
