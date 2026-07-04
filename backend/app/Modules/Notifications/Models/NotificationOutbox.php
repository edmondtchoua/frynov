<?php

namespace App\Modules\Notifications\Models;

use App\Shared\Traits\HasTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * RC-6A — notification rendue, en attente d'envoi ou journalisée (sent/failed).
 */
class NotificationOutbox extends Model
{
    use HasTenant, HasUuids;

    public const STATUS_PENDING = 'pending';
    public const STATUS_SENT    = 'sent';
    public const STATUS_FAILED  = 'failed';

    public const MAX_ATTEMPTS = 3;

    protected $table = 'notification_outbox';

    protected $fillable = [
        'tenant_id', 'channel_id', 'channel', 'template_code',
        'recipient', 'subject', 'body',
        'status', 'attempts', 'last_error', 'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'sent_at'  => 'datetime',
        ];
    }

    public function toApiArray(): array
    {
        return [
            'id'            => $this->id,
            'channel'       => $this->channel,
            'template_code' => $this->template_code,
            'recipient'     => $this->recipient,
            'subject'       => $this->subject,
            'status'        => $this->status,
            'attempts'      => $this->attempts,
            'last_error'    => $this->last_error,
            'sent_at'       => $this->sent_at?->toISOString(),
            'created_at'    => $this->created_at?->toISOString(),
        ];
    }
}
