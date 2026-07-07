<?php

namespace App\Modules\Accounting\Models;

use App\Shared\Traits\HasTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * RC-26 — événement métier à comptabiliser (outbox du moteur d'imputation).
 * Unique par (tenant, event_type, source) → un rejeu (retry, resync offline) ne produit
 * jamais deux écritures pour la même opération.
 */
class OutboxEvent extends Model
{
    use HasTenant, HasUuids;

    public const STATUS_PENDING   = 'pending';
    public const STATUS_PROCESSED = 'processed';
    public const STATUS_FAILED    = 'failed';
    public const STATUS_SKIPPED   = 'skipped';

    public const MAX_ATTEMPTS = 5;

    protected $table = 'accounting_outbox';

    protected $fillable = [
        'tenant_id', 'event_type', 'source_type', 'source_id',
        'payload', 'status', 'attempts', 'last_error', 'entry_id',
    ];

    protected function casts(): array
    {
        return ['payload' => 'array', 'attempts' => 'integer'];
    }
}
