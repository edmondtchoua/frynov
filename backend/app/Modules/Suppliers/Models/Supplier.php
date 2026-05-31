<?php

namespace App\Modules\Suppliers\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'code',
        'name',
        'email',
        'phone',
        'contact_name',
        'address',
        'payment_terms',
        'notes',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'address' => 'array',
        ];
    }

    // ── Relations ─────────────────────────────────────────────────────────────

    public function products(): HasMany
    {
        return $this->hasMany(\App\Modules\Catalog\Models\Product::class);
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeForTenant(Builder $query, string $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeSearch(Builder $query, string $term): Builder
    {
        $like = "%{$term}%";

        return $query->where(function (Builder $q) use ($like) {
            $q->where('name',         'like', $like)
              ->orWhere('code',        'like', $like)
              ->orWhere('email',       'like', $like)
              ->orWhere('contact_name','like', $like);
        });
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function isActive(): bool { return $this->status === 'active'; }
}
