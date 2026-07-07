<?php

namespace App\Modules\Demo\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Demande de démonstration d'un prospect.
 *
 * PLATFORM-LEVEL : volontairement SANS trait HasTenant — cet objet n'appartient
 * à aucun tenant et n'est visible que du back-office super-admin.
 */
class DemoRequest extends Model
{
    use HasUuids, SoftDeletes;

    public const STATUS_NEW            = 'new';
    public const STATUS_PENDING_REVIEW = 'pending_review';
    public const STATUS_APPROVED       = 'approved';
    public const STATUS_ACCESS_SENT    = 'demo_access_sent';
    public const STATUS_REJECTED       = 'rejected';
    public const STATUS_EXPIRED        = 'expired';
    public const STATUS_CONVERTED      = 'converted_to_customer';

    public const STATUSES = [
        self::STATUS_NEW,
        self::STATUS_PENDING_REVIEW,
        self::STATUS_APPROVED,
        self::STATUS_ACCESS_SENT,
        self::STATUS_REJECTED,
        self::STATUS_EXPIRED,
        self::STATUS_CONVERTED,
    ];

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'company',
        'country',
        'primary_need',
        'modules',
        'message',
        'consent_contact',
        'consent_demo_email',
        'source',
        'locale',
        'ip_address',
        'user_agent',
        'status',
        'internal_notes',
        'reviewed_by',
        'reviewed_at',
        'rejection_reason',
        'demo_tenant_id',
        'demo_user_id',
        'demo_access_expires_at',
        'access_sent_at',
        'access_email_status',
        'last_demo_login_at',
        'reminder_sent_at',
        'ended_email_sent_at',
        'converted_at',
    ];

    protected function casts(): array
    {
        return [
            'modules'                => 'array',
            'consent_contact'        => 'boolean',
            'consent_demo_email'     => 'boolean',
            'reviewed_at'            => 'datetime',
            'demo_access_expires_at' => 'datetime',
            'access_sent_at'         => 'datetime',
            'last_demo_login_at'     => 'datetime',
            'reminder_sent_at'       => 'datetime',
            'ended_email_sent_at'    => 'datetime',
            'converted_at'           => 'datetime',
        ];
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    public function fullName(): string
    {
        return trim("{$this->first_name} {$this->last_name}") ?: $this->email;
    }

    public function isActiveDemo(): bool
    {
        return $this->status === self::STATUS_ACCESS_SENT
            && $this->demo_access_expires_at !== null
            && $this->demo_access_expires_at->isFuture();
    }

    public function isExpired(): bool
    {
        return $this->demo_access_expires_at !== null
            && $this->demo_access_expires_at->isPast();
    }

    // ── Scopes ─────────────────────────────────────────────────────────────────

    public function scopeStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeSearch(Builder $query, string $term): Builder
    {
        $like = '%'.$term.'%';

        return $query->where(function (Builder $q) use ($like) {
            $q->where('email', 'like', $like)
              ->orWhere('first_name', 'like', $like)
              ->orWhere('last_name', 'like', $like)
              ->orWhere('company', 'like', $like);
        });
    }

    /** Accès démo envoyés, expirés, pas encore marqués (candidats à la révocation). */
    public function scopeDueForExpiry(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACCESS_SENT)
            ->whereNotNull('demo_access_expires_at')
            ->where('demo_access_expires_at', '<=', now());
    }
}
