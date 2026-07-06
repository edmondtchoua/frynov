<?php

namespace App\Modules\Billing\Models;

use App\Models\User;
use App\Modules\Tenants\Models\Tenant;
use App\Shared\Traits\HasTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Demande de changement de plan (P1) — objet de premier plan qui porte le cycle de vie d'un upgrade/
 * downgrade/changement de périodicité. Un paiement manuel (`ManualPayment`) est une PIÈCE rattachée.
 *
 * Machine à états (transitions autorisées dans {@see TRANSITIONS}) :
 *   draft → submitted → pending_payment | pending_validation → approved → activated
 *   (+ sorties rejected / cancelled / expired / failed).
 *
 * Les montants (`*_minor`) figent le devis autoritatif (P0) ; `plan_snapshot` fige les conditions du
 * plan cible → une modification ultérieure du plan n'altère JAMAIS une demande existante (audit G6).
 */
class SubscriptionChangeRequest extends Model
{
    use HasTenant, HasUuids, SoftDeletes;

    public const STATUS_DRAFT              = 'draft';
    public const STATUS_SUBMITTED          = 'submitted';
    public const STATUS_PENDING_PAYMENT    = 'pending_payment';
    public const STATUS_PENDING_VALIDATION = 'pending_validation';
    public const STATUS_APPROVED           = 'approved';
    public const STATUS_REJECTED           = 'rejected';
    public const STATUS_CANCELLED          = 'cancelled';
    public const STATUS_EXPIRED            = 'expired';
    public const STATUS_ACTIVATED          = 'activated';
    public const STATUS_FAILED             = 'failed';

    public const TYPE_UPGRADE      = 'upgrade';
    public const TYPE_DOWNGRADE    = 'downgrade';
    public const TYPE_CROSSGRADE   = 'crossgrade';
    public const TYPE_PERIODICITY  = 'periodicity';
    public const TYPE_REACTIVATION = 'reactivation';

    public const EFFECTIVE_IMMEDIATE  = 'immediate';
    public const EFFECTIVE_NEXT_CYCLE = 'next_cycle';

    /** États encore « vivants » (une action reste possible). */
    public const OPEN_STATUSES = [
        self::STATUS_DRAFT, self::STATUS_SUBMITTED, self::STATUS_PENDING_PAYMENT,
        self::STATUS_PENDING_VALIDATION, self::STATUS_APPROVED,
    ];

    /** États terminaux (aucune transition sortante). */
    public const TERMINAL_STATUSES = [
        self::STATUS_REJECTED, self::STATUS_CANCELLED, self::STATUS_EXPIRED,
        self::STATUS_ACTIVATED, self::STATUS_FAILED,
    ];

    /** Transitions autorisées : from => [to, …]. Toute autre transition est refusée. */
    public const TRANSITIONS = [
        self::STATUS_DRAFT              => [self::STATUS_SUBMITTED, self::STATUS_CANCELLED],
        self::STATUS_SUBMITTED          => [self::STATUS_PENDING_PAYMENT, self::STATUS_PENDING_VALIDATION, self::STATUS_CANCELLED, self::STATUS_EXPIRED],
        self::STATUS_PENDING_PAYMENT    => [self::STATUS_PENDING_VALIDATION, self::STATUS_ACTIVATED, self::STATUS_CANCELLED, self::STATUS_EXPIRED, self::STATUS_FAILED],
        self::STATUS_PENDING_VALIDATION => [self::STATUS_APPROVED, self::STATUS_ACTIVATED, self::STATUS_REJECTED, self::STATUS_PENDING_PAYMENT, self::STATUS_CANCELLED, self::STATUS_EXPIRED],
        self::STATUS_APPROVED           => [self::STATUS_ACTIVATED, self::STATUS_FAILED],
        self::STATUS_REJECTED           => [],
        self::STATUS_CANCELLED          => [],
        self::STATUS_EXPIRED            => [],
        self::STATUS_ACTIVATED          => [],
        self::STATUS_FAILED             => [],
    ];

    protected $fillable = [
        'tenant_id', 'from_plan_id', 'to_plan_id',
        'interval', 'quantity', 'market_code', 'currency', 'change_type', 'effective', 'status',
        'base_gross_minor', 'promo_code', 'promo_discount_minor', 'tax_minor', 'setup_fee_minor', 'proration_credit_minor', 'net_payable_minor',
        'plan_snapshot', 'metadata', 'notes',
        'requested_by', 'reviewed_by', 'review_notes', 'rejection_reason',
        'submitted_at', 'reviewed_at', 'activated_at', 'cancelled_at', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity'               => 'integer',
            'base_gross_minor'       => 'integer',
            'promo_discount_minor'   => 'integer',
            'tax_minor'              => 'integer',
            'setup_fee_minor'        => 'integer',
            'proration_credit_minor' => 'integer',
            'net_payable_minor'      => 'integer',
            'plan_snapshot'          => 'array',
            'metadata'               => 'array',
            'submitted_at'           => 'datetime',
            'reviewed_at'            => 'datetime',
            'activated_at'           => 'datetime',
            'cancelled_at'           => 'datetime',
            'expires_at'             => 'datetime',
        ];
    }

    // ── State helpers ───────────────────────────────────────────────────────────

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true);
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, self::TERMINAL_STATUSES, true);
    }

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, self::TRANSITIONS[$this->status] ?? [], true);
    }

    // ── Relations ───────────────────────────────────────────────────────────────

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function fromPlan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'from_plan_id');
    }

    public function toPlan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'to_plan_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(ManualPayment::class, 'change_request_id');
    }

    // ── Sérialisation ─────────────────────────────────────────────────────────────

    /** @return array<string,mixed> Payload tenant-facing. */
    public function toApiArray(): array
    {
        return [
            'id'                     => $this->id,
            'status'                 => $this->status,
            'change_type'            => $this->change_type,
            'effective'              => $this->effective,
            'interval'               => $this->interval,
            'quantity'               => $this->quantity,
            'currency'               => $this->currency,
            'market_code'            => $this->market_code,
            'from_plan_code'         => $this->plan_snapshot['from_plan_code'] ?? null,
            'to_plan_code'           => $this->plan_snapshot['plan_code'] ?? null,
            'base_gross_minor'       => $this->base_gross_minor,
            'promo_code'             => $this->promo_code,
            'promo_discount_minor'   => $this->promo_discount_minor,
            'proration_credit_minor' => $this->proration_credit_minor,
            'net_payable_minor'      => $this->net_payable_minor,
            'plan_snapshot'          => $this->plan_snapshot,
            'rejection_reason'       => $this->rejection_reason,
            'submitted_at'           => $this->submitted_at,
            'activated_at'           => $this->activated_at,
            'created_at'             => $this->created_at,
        ];
    }
}
