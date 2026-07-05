<?php

namespace App\Modules\Auth\Models;

use App\Models\User;
use App\Shared\Traits\HasTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A time-boxed role grant: a member receives a tenant role until `expires_at`, after
 * which it is revoked automatically (scheduled command) — no manual intervention.
 */
class TemporaryAccessGrant extends Model
{
    use HasTenant, HasUuids;

    protected $fillable = [
        'tenant_id', 'user_id', 'role', 'granted_by', 'expires_at', 'revoked_at', 'note',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Live grants: not revoked, not yet expired. */
    public function scopeActive(Builder $q): Builder
    {
        return $q->whereNull('revoked_at')->where('expires_at', '>', now());
    }

    /** Grants that have lapsed but not yet been revoked (the scheduler's work list). */
    public function scopeDue(Builder $q): Builder
    {
        return $q->whereNull('revoked_at')->where('expires_at', '<=', now());
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null && $this->expires_at->isFuture();
    }
}
