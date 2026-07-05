<?php

namespace App\Modules\Notifications\Models;

use App\Shared\Traits\HasTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * RC-6A — canal d'envoi configurable par tenant (email SMTP, proxy API SMS/WhatsApp, log).
 * La `config` (credentials) est chiffrée au repos et n'est JAMAIS renvoyée en clair par l'API.
 */
class NotificationChannel extends Model
{
    use HasTenant, HasUuids, SoftDeletes;

    public const CHANNEL_EMAIL    = 'email';
    public const CHANNEL_SMS      = 'sms';
    public const CHANNEL_WHATSAPP = 'whatsapp';
    public const CHANNELS = [self::CHANNEL_EMAIL, self::CHANNEL_SMS, self::CHANNEL_WHATSAPP];

    public const PROVIDER_SMTP     = 'smtp';
    public const PROVIDER_HTTP_API = 'http_api';
    public const PROVIDER_LOG      = 'log';
    public const PROVIDERS = [self::PROVIDER_SMTP, self::PROVIDER_HTTP_API, self::PROVIDER_LOG];

    protected $table = 'notification_channels';

    protected $fillable = [
        'tenant_id', 'channel', 'provider', 'name',
        'config', 'from_name', 'from_address',
        'is_active', 'is_default',
    ];

    protected function casts(): array
    {
        return [
            'config'     => 'encrypted:array',
            'is_active'  => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    /** Payload API — la config est réduite à ses CLÉS (jamais les secrets en clair). */
    public function toApiArray(): array
    {
        return [
            'id'           => $this->id,
            'channel'      => $this->channel,
            'provider'     => $this->provider,
            'name'         => $this->name,
            'from_name'    => $this->from_name,
            'from_address' => $this->from_address,
            'config_keys'  => array_keys($this->config ?? []),
            'is_active'    => $this->is_active,
            'is_default'   => $this->is_default,
            'created_at'   => $this->created_at?->toISOString(),
        ];
    }
}
