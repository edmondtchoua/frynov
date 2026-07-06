<?php

namespace App\Modules\Tenants\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tenant extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'domain',
        'plan',
        'status',
        'onboarded',
        'subscription_status',
        'is_demo',
        'demo_expires_at',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'onboarded'       => 'boolean',
            'is_demo'         => 'boolean',
            'demo_expires_at' => 'datetime',
            'settings'        => 'array',
            'created_at'      => 'datetime',
            'updated_at'      => 'datetime',
        ];
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isDemo(): bool
    {
        return (bool) $this->is_demo;
    }

    /**
     * Vrai si ce tenant est un tenant démo dont l'accès a expiré.
     */
    public function isDemoExpired(): bool
    {
        return $this->isDemo()
            && $this->demo_expires_at !== null
            && $this->demo_expires_at->isPast();
    }

    /**
     * Scope : uniquement les tenants démo expirés (candidats à la révocation).
     */
    public function scopeDemoExpired($query)
    {
        return $query->where('is_demo', true)
            ->whereNotNull('demo_expires_at')
            ->where('demo_expires_at', '<=', now());
    }

    // ── Relations ──────────────────────────────────────────────────────────────

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
