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

    protected $fillable = [
        'email', 'password', 'verification_code', 'verification_expires_at', 'verified_at', 'last_login_at',
    ];

    protected $hidden = ['password', 'verification_code'];

    protected function casts(): array
    {
        return [
            'password'                => 'hashed',
            'verification_expires_at' => 'datetime',
            'verified_at'             => 'datetime',
            'last_login_at'           => 'datetime',
        ];
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
