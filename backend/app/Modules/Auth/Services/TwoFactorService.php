<?php

namespace App\Modules\Auth\Services;

use App\Models\User;
use App\Modules\Auth\Mail\TwoFactorCodeMail;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

/**
 * RC-13 F-4 — 2FA par code email (opt-in). À la connexion d'un compte 2FA activé, un code est envoyé
 * à son email ; le token n'est délivré qu'après vérification du code.
 */
class TwoFactorService
{
    public const CODE_TTL_MINUTES = 10;
    public const MAX_ATTEMPTS = 5;

    private const TABLE = 'two_factor_codes';

    /** Génère et envoie un code de connexion à l'email du compte. */
    public function challenge(User $user): void
    {
        $email = strtolower((string) $user->email);
        $code = (string) random_int(100000, 999999);

        DB::table(self::TABLE)->updateOrInsert(
            ['email' => $email],
            [
                'code_hash'  => Hash::make($code),
                'attempts'   => 0,
                'expires_at' => now()->addMinutes(self::CODE_TTL_MINUTES),
                'created_at' => now(),
            ],
        );

        Mail::to($email)->send(new TwoFactorCodeMail($code, $user->name ?? '', self::CODE_TTL_MINUTES));
    }

    /**
     * Vérifie un code de challenge. @return User|null l'utilisateur si le code est valide.
     */
    public function verify(string $email, string $code, ?string $tenantId = null): ?User
    {
        $email = strtolower(trim($email));
        $row = DB::table(self::TABLE)->where('email', $email)->first();

        if (! $row || Carbon::parse($row->expires_at)->isPast() || (int) $row->attempts >= self::MAX_ATTEMPTS) {
            return null;
        }

        if (! Hash::check($code, $row->code_hash)) {
            DB::table(self::TABLE)->where('email', $email)->increment('attempts');

            return null;
        }

        $query = User::where('email', $email);
        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }
        $user = $query->first();
        if (! $user) {
            return null;
        }

        DB::table(self::TABLE)->where('email', $email)->delete();

        return $user;
    }
}
