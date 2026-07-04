<?php

namespace App\Modules\Notifications\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * RC-6A — modèle de notification (placeholders {{var}}). `tenant_id` null = modèle global par défaut.
 * PAS de HasTenant : la résolution tenant→global exige de lire les lignes globales (tenant_id null).
 */
class NotificationTemplate extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'notification_templates';

    protected $fillable = [
        'tenant_id', 'code', 'channel', 'locale', 'subject', 'body', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function toApiArray(): array
    {
        return [
            'id'         => $this->id,
            'tenant_id'  => $this->tenant_id,      // null = modèle global (lecture seule côté tenant)
            'code'       => $this->code,
            'channel'    => $this->channel,
            'locale'     => $this->locale,
            'subject'    => $this->subject,
            'body'       => $this->body,
            'is_active'  => $this->is_active,
            'is_global'  => $this->tenant_id === null,
        ];
    }
}
