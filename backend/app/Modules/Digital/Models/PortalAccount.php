<?php

namespace App\Modules\Digital\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * RC-7C — compte portail du client final (email global, hors multi-tenant). Login refusé tant que
 * l'email n'est pas vérifié par code.
 */
class PortalAccount extends Authenticatable
{
    use HasApiTokens, HasUuids;

    protected $table = 'portal_accounts';

    /** Au-delà, le code de vérification est invalidé (anti brute-force). */
    public const MAX_VERIFY_ATTEMPTS = 5;

    protected $fillable = [
        'email', 'password', 'verification_code', 'verification_expires_at', 'verification_attempts',
        'verified_at', 'last_login_at',
    ];

    protected $hidden = ['password', 'verification_code'];

    protected function casts(): array
    {
        return [
            'password'                => 'hashed',
            'verification_expires_at' => 'datetime',
            'verification_attempts'   => 'integer',
            'verified_at'             => 'datetime',
            'last_login_at'           => 'datetime',
        ];
    }

    /** Un code non expiré est-il encore en attente de vérification (pour ne pas re-spammer un envoi) ? */
    public function hasPendingCode(): bool
    {
        return $this->verification_code !== null
            && $this->verification_expires_at?->isFuture()
            && $this->verification_attempts < self::MAX_VERIFY_ATTEMPTS;
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    /**
     * Compat TenantScope (appelé sur tout user authentifié) : un compte portail n'est jamais
     * super-admin et n'a pas de tenant — les requêtes du portail scoppent explicitement.
     */
    public function isSuperAdmin(): bool
    {
        return false;
    }
}
