<?php

namespace App\Modules\Auth\Services;

use App\Models\User;
use App\Modules\Auth\Mail\EmailChangeCodeMail;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

/**
 * RC-11 F-6 — changement d'email vérifié. Le nouvel email n'est appliqué qu'après saisie d'un code
 * envoyé à cette nouvelle adresse (empêche de « voler » l'email d'un tiers ou une faute de frappe).
 */
class EmailChangeService
{
    public const CODE_TTL_MINUTES = 30;
    public const MAX_ATTEMPTS = 5;

    private const TABLE = 'email_change_requests';

    /** Enregistre une demande et envoie le code à la NOUVELLE adresse. */
    public function request(User $user, string $newEmail): void
    {
        $newEmail = strtolower(trim($newEmail));
        $code = (string) random_int(100000, 999999);

        DB::table(self::TABLE)->updateOrInsert(
            ['user_id' => $user->id],
            [
                'new_email'  => $newEmail,
                'code_hash'  => Hash::make($code),
                'attempts'   => 0,
                'expires_at' => now()->addMinutes(self::CODE_TTL_MINUTES),
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );

        Mail::to($newEmail)->send(new EmailChangeCodeMail($code, $user->name ?? '', self::CODE_TTL_MINUTES));
    }

    /**
     * Confirme le code et applique le nouvel email.
     * @return array{ok:bool,reason?:string,email?:string}
     */
    public function confirm(User $user, string $code): array
    {
        $row = DB::table(self::TABLE)->where('user_id', $user->id)->first();

        if (! $row || Carbon::parse($row->expires_at)->isPast() || (int) $row->attempts >= self::MAX_ATTEMPTS) {
            return ['ok' => false, 'reason' => 'invalid'];
        }

        if (! Hash::check($code, $row->code_hash)) {
            DB::table(self::TABLE)->where('user_id', $user->id)->increment('attempts');

            return ['ok' => false, 'reason' => 'invalid'];
        }

        // Re-contrôle d'unicité au moment de l'application (l'email a pu être pris entre-temps).
        $taken = User::where('email', $row->new_email)->where('id', '!=', $user->id)->exists();
        if ($taken) {
            DB::table(self::TABLE)->where('user_id', $user->id)->delete();

            return ['ok' => false, 'reason' => 'taken'];
        }

        $user->update(['email' => $row->new_email]);
        DB::table(self::TABLE)->where('user_id', $user->id)->delete();

        return ['ok' => true, 'email' => $row->new_email];
    }

    /** Email en attente de confirmation (pour l'affichage), ou null. */
    public function pendingEmail(User $user): ?string
    {
        $row = DB::table(self::TABLE)->where('user_id', $user->id)->first();
        if (! $row || Carbon::parse($row->expires_at)->isPast()) {
            return null;
        }

        return $row->new_email;
    }
}
