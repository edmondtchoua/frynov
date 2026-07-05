<?php

namespace App\Modules\Auth\Services;

use App\Models\User;
use App\Modules\Auth\Mail\PasswordResetCodeMail;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

/**
 * RC-10 F-3 — réinitialisation de mot de passe par code envoyé par email.
 *
 * `request()` : génère un code (6 chiffres), le stocke HACHÉ et l'envoie à l'email s'il correspond à un
 *   utilisateur — réponse toujours générique côté contrôleur (anti-énumération).
 * `reset()` : vérifie le code (haché, non expiré, sous le seuil de tentatives), applique le nouveau mot
 *   de passe et RÉVOQUE toutes les sessions (tokens), puis purge le code.
 */
class PasswordResetService
{
    public const CODE_TTL_MINUTES = 30;
    public const MAX_ATTEMPTS = 5;

    private const TABLE = 'password_reset_codes';

    /** Génère et envoie un code si l'email correspond à un utilisateur (sinon no-op silencieux). */
    public function request(string $email): void
    {
        $email = strtolower(trim($email));
        $user = User::where('email', $email)->first();
        if (! $user) {
            return; // anti-énumération : le contrôleur répond génériquement
        }

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

        Mail::to($email)->send(new PasswordResetCodeMail($code, $user->name ?? '', self::CODE_TTL_MINUTES));
    }

    /** Applique un nouveau mot de passe si le code est valide. @return bool succès */
    public function reset(string $email, string $code, string $password): bool
    {
        $email = strtolower(trim($email));
        $row = DB::table(self::TABLE)->where('email', $email)->first();

        if (! $row
            || Carbon::parse($row->expires_at)->isPast()
            || (int) $row->attempts >= self::MAX_ATTEMPTS) {
            return false;
        }

        if (! Hash::check($code, $row->code_hash)) {
            DB::table(self::TABLE)->where('email', $email)->increment('attempts');

            return false;
        }

        $user = User::where('email', $email)->first();
        if (! $user) {
            return false;
        }

        // Le cast `hashed` du modèle chiffre le mot de passe — on passe la valeur en clair.
        $user->update(['password' => $password]);
        $user->tokens()->delete();                 // invalide toutes les sessions ouvertes
        DB::table(self::TABLE)->where('email', $email)->delete();

        return true;
    }
}
