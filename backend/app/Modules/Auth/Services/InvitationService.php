<?php

namespace App\Modules\Auth\Services;

use App\Models\User;
use App\Modules\Auth\Mail\UserInvitationMail;
use App\Modules\Tenants\Models\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

/**
 * RC-12 F-5 — invitations d'équipe par email. Remplace le mot de passe temporaire renvoyé en réponse
 * API par un **code d'activation envoyé par email** : le membre choisit lui-même son mot de passe.
 */
class InvitationService
{
    public const CODE_TTL_DAYS = 7;
    public const MAX_ATTEMPTS = 5;

    private const TABLE = 'user_invitations';

    /** Crée l'invitation pour un utilisateur fraîchement créé et envoie le code par email. */
    public function invite(User $invitee, User $inviter): void
    {
        $code = (string) random_int(100000, 999999);

        DB::table(self::TABLE)->updateOrInsert(
            ['user_id' => $invitee->id],
            [
                'tenant_id'   => $invitee->tenant_id,
                'code_hash'   => Hash::make($code),
                'attempts'    => 0,
                'expires_at'  => now()->addDays(self::CODE_TTL_DAYS),
                'invited_by'  => $inviter->id,
                'accepted_at' => null,
                'updated_at'  => now(),
                'created_at'  => now(),
            ],
        );

        $tenant = Tenant::withoutGlobalScopes()->find($invitee->tenant_id);
        $acceptUrl = rtrim((string) config('app.frontend_url'), '/')
            . '/accept-invitation?email=' . rawurlencode((string) $invitee->email);

        Mail::to($invitee->email)->send(new UserInvitationMail(
            $code,
            $invitee->name ?? '',
            $tenant?->name ?? 'Frynov',
            $inviter->name ?? '',
            $acceptUrl,
            self::CODE_TTL_DAYS,
        ));
    }

    /** Le compte a-t-il une invitation encore en attente (non acceptée) ? */
    public function hasPending(User $user): bool
    {
        $row = DB::table(self::TABLE)->where('user_id', $user->id)->whereNull('accepted_at')->first();

        return $row !== null && ! Carbon::parse($row->expires_at)->isPast();
    }

    /**
     * Accepte l'invitation : vérifie le code et pose le mot de passe choisi. @return bool succès
     */
    public function accept(string $email, string $code, string $password): bool
    {
        $email = strtolower(trim($email));
        $user = User::where('email', $email)->first();
        if (! $user) {
            return false;
        }

        $row = DB::table(self::TABLE)->where('user_id', $user->id)->first();
        if (! $row
            || $row->accepted_at !== null
            || Carbon::parse($row->expires_at)->isPast()
            || (int) $row->attempts >= self::MAX_ATTEMPTS) {
            return false;
        }

        if (! Hash::check($code, $row->code_hash)) {
            DB::table(self::TABLE)->where('user_id', $user->id)->increment('attempts');

            return false;
        }

        $user->update(['password' => $password]); // cast `hashed`
        DB::table(self::TABLE)->where('user_id', $user->id)->update(['accepted_at' => now(), 'updated_at' => now()]);

        return true;
    }
}
