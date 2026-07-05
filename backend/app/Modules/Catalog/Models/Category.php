<?php

namespace App\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Shared\Traits\HasTenant;

class Category extends Model
{
    use HasFactory, HasTenant, HasUuids, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'parent_id',
        'name',
        'slug',
        'description',
        'sort_order',
        'is_active',
        'depth',
        'path',
    ];

    protected function casts(): array
    {
        return [
            'is_active'  => 'boolean',
            'sort_order' => 'integer',
            'depth'      => 'integer',
        ];
    }

    // ── Materialised path: auto-computed on create/parent change ──────────────

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Category $cat) {
            $cat->depth = 0;
            $cat->path  = (string) $cat->id; // will be overwritten if parent exists

            if ($cat->parent_id) {
                $parent = static::withoutTenantScope()
                    ->where('id', $cat->parent_id)
                    ->where('tenant_id', $cat->tenant_id) // applicative cross-tenant guard
                    ->firstOrFail();

                $cat->depth = $parent->depth + 1;
                $cat->path  = $parent->path . '/' . $cat->id;
            }
        });

        // After creation the ID is available — set the root path if no parent
        static::created(function (Category $cat) {
            if (! $cat->parent_id && $cat->path !== $cat->id) {
                $cat->updateQuietly(['path' => $cat->id]);
            }
        });

        // Rebuild subtree paths when a category is moved
        static::updating(function (Category $cat) {
            if ($cat->isDirty('parent_id')) {
                $cat->recalculatePath();
            }
        });

        static::updated(function (Category $cat) {
            if ($cat->wasChanged('path')) {
                // Cascade path update to all descendants
                $cat->rebuildDescendantPaths();
            }
        });
    }

    // ── Path helpers ─────────────────────────────────────────────────────────

    private function recalculatePath(): void
    {
        if ($this->parent_id) {
            $parent = static::withoutTenantScope()->findOrFail($this->parent_id);
            $this->depth = $parent->depth + 1;
            $this->path  = $parent->path . '/' . $this->id;
        } else {
            $this->depth = 0;
            $this->path  = $this->id;
        }
    }

    private function rebuildDescendantPaths(): void
    {
        $children = static::withoutTenantScope()
            ->where('parent_id', $this->id)
            ->get();

        foreach ($children as $child) {
            $child->updateQuietly([
                'depth' => $this->depth + 1,
                'path'  => $this->path . '/' . $child->id,
            ]);
            $child->rebuildDescendantPaths(); // recursive
        }
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    /** All descendants of a given category (uses materialised path for O(1) query) */
    public function scopeDescendantsOf(Builder $q, string $categoryId): Builder
    {
        $root = static::findOrFail($categoryId);
        return $q->where('path', 'like', $root->path . '/%');
    }

    /** Top-level categories only */
    public function scopeRoots(Builder $q): Builder
    {
        return $q->whereNull('parent_id')->orderBy('sort_order');
    }

    /** Active categories only */
    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    // ── Relations ─────────────────────────────────────────────────────────────

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id')->orderBy('sort_order');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
